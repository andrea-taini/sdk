<?php

/**
 * Copyright © 2019-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace SprykerSdk\Sdk\Infrastructure\Repository;

use SprykerSdk\Sdk\Core\Appplication\Dependency\Repository\SettingRepositoryInterface;
use SprykerSdk\Sdk\Core\Appplication\Dependency\Repository\TaskRepositoryInterface;
use SprykerSdk\Sdk\Core\Appplication\Exception\MissingSettingException;
use SprykerSdk\Sdk\Core\Domain\Entity\Command;
use SprykerSdk\Sdk\Core\Domain\Entity\Converter;
use SprykerSdk\Sdk\Core\Domain\Entity\File;
use SprykerSdk\Sdk\Core\Domain\Entity\Lifecycle\InitializedEventData;
use SprykerSdk\Sdk\Core\Domain\Entity\Lifecycle\Lifecycle;
use SprykerSdk\Sdk\Core\Domain\Entity\Lifecycle\RemovedEventData;
use SprykerSdk\Sdk\Core\Domain\Entity\Lifecycle\UpdatedEventData;
use SprykerSdk\Sdk\Core\Domain\Entity\Placeholder;
use SprykerSdk\Sdk\Core\Domain\Entity\Task;
use SprykerSdk\SdkContracts\Entity\ContextInterface;
use SprykerSdk\SdkContracts\Entity\Lifecycle\TaskLifecycleInterface;
use SprykerSdk\SdkContracts\Entity\TaggedTaskInterface;
use SprykerSdk\SdkContracts\Entity\TaskInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;

class TaskYamlRepository implements TaskRepositoryInterface
{
    /**
     * @var string
     */
    protected const TASK_SET_TYPE = 'task_set';

    /**
     * @var \SprykerSdk\Sdk\Core\Appplication\Dependency\Repository\SettingRepositoryInterface
     */
    protected SettingRepositoryInterface $settingRepository;

    /**
     * @var \Symfony\Component\Finder\Finder
     */
    protected Finder $fileFinder;

    /**
     * @var \Symfony\Component\Yaml\Yaml
     */
    protected Yaml $yamlParser;

    /**
     * @var iterable<\SprykerSdk\SdkContracts\Entity\TaskInterface>
     */
    protected iterable $existingTasks = [];

    /**
     * @param \SprykerSdk\Sdk\Core\Appplication\Dependency\Repository\SettingRepositoryInterface $settingRepository
     * @param \Symfony\Component\Finder\Finder $fileFinder
     * @param \Symfony\Component\Yaml\Yaml $yamlParser
     * @param iterable<\SprykerSdk\SdkContracts\Entity\TaskInterface> $existingTasks
     */
    public function __construct(
        SettingRepositoryInterface $settingRepository,
        Finder $fileFinder,
        Yaml $yamlParser,
        iterable $existingTasks = []
    ) {
        $this->existingTasks = $existingTasks;
        $this->yamlParser = $yamlParser;
        $this->fileFinder = $fileFinder;
        $this->settingRepository = $settingRepository;
    }

    /**
     * @param array $tags
     *
     * @throws \SprykerSdk\Sdk\Core\Appplication\Exception\MissingSettingException
     *
     * @return array
     */
    public function findAll(array $tags = []): array
    {
        $taskDirSetting = $this->settingRepository->findOneByPath('extension_dirs');

        if (!$taskDirSetting || !is_array($taskDirSetting->getValues())) {
            throw new MissingSettingException('extension_dirs are not configured properly');
        }

        $tasks = [];
        $taskListData = [];
        $taskSetsData = [];

        $finder = $this->fileFinder->in(array_map(function (string $directory): string {
            return $directory . '/*/Tasks/';
        }, $taskDirSetting->getValues()))->name('*.yaml');

        //read task from path, parse and create Task, later use DB for querying
        foreach ($finder->files() as $taskFile) {
            $taskData = $this->yamlParser->parse($taskFile->getContents());

            if ($taskData['type'] === static::TASK_SET_TYPE) {
                $taskSetsData[$taskData['id']] = $taskData;
            } else {
                $taskListData[$taskData['id']] = $taskData;
            }
        }

        foreach ($taskListData as $taskData) {
            $task = $this->buildTask($taskData, $taskListData, $tags);
            $tasks[$task->getId()] = $task;
        }

        foreach ($taskSetsData as $taskData) {
            $task = $this->buildTaskSet($taskData, $taskListData, $tasks, $tags);
            $tasks[$task->getId()] = $task;
        }

        foreach ($this->existingTasks as $existingTask) {
            $tasks[$existingTask->getId()] = $existingTask;
        }

        return $tasks;
    }

    /**
     * @param string $taskId
     * @param array $tags
     *
     * @return \SprykerSdk\SdkContracts\Entity\TaskInterface|null
     */
    public function findById(string $taskId, array $tags = []): ?TaskInterface
    {
        $tasks = $this->findAll($tags);

        if (array_key_exists($taskId, $tasks)) {
            return $tasks[$taskId];
        }

        return null;
    }

    /**
     * @param array $data
     * @param array $taskListData
     * @param array $tags
     *
     * @return array<\SprykerSdk\SdkContracts\Entity\PlaceholderInterface>
     */
    protected function buildPlaceholders(array $data, array $taskListData, array $tags = []): array
    {
        $placeholders = [];
        $taskPlaceholders = [];
        $taskPlaceholders[] = $data['placeholders'] ?? [];

        if (isset($data['type']) && $data['type'] === static::TASK_SET_TYPE) {
            foreach ($data['tasks'] as $task) {
                $taskTags = $task['tags'] ?? [];
                if ($tags && !array_intersect($tags, $taskTags)) {
                    continue;
                }
                $taskPlaceholders[] = $taskListData[$task['id']]['placeholders'];
            }
        }
        $taskPlaceholders = array_merge(...$taskPlaceholders);

        foreach ($taskPlaceholders as $placeholderData) {
            $placeholderName = $placeholderData['name'];
            $placeholders[$placeholderName] = new Placeholder(
                $placeholderName,
                $placeholderData['value_resolver'],
                $placeholderData['configuration'] ?? [],
                $placeholderData['optional'] ?? false,
            );
        }

        return $placeholders;
    }

    /**
     * @param array $data
     * @param array $taskListData
     * @param array<string> $tags
     *
     * @return array<\SprykerSdk\Sdk\Core\Domain\Entity\Command>
     */
    protected function buildCommands(array $data, array $taskListData, array $tags = []): array
    {
        $commands = [];

        if ($data['type'] === 'local_cli') {
            $converter = isset($data['report_converter']) ? new Converter(
                $data['report_converter']['name'],
                $data['report_converter']['configuration'],
            ) : null;
            $commands[] = new Command(
                $data['command'],
                $data['type'],
                false,
                [],
                $converter,
            );
        }

        if ($data['type'] === static::TASK_SET_TYPE) {
            foreach ($data['tasks'] as $task) {
                $tasksTags = $task['tags'] ?? [];
                if ($tags && !array_intersect($tags, $tasksTags)) {
                    continue;
                }
                $converter = isset($taskListData[$task['id']]['report_converter']) ? new Converter(
                    $taskListData[$task['id']]['report_converter']['name'],
                    $taskListData[$task['id']]['report_converter']['configuration'],
                ) : null;

                $commands[] = new Command(
                    $taskListData[$task['id']]['command'],
                    $taskListData[$task['id']]['type'],
                    $task['stop_on_error'],
                    $tasksTags,
                    $converter,
                );
            }
        }

        return $commands;
    }

    /**
     * @param array $data
     *
     * @return array<\SprykerSdk\SdkContracts\Entity\CommandInterface>
     */
    protected function buildLifecycleCommands(array $data): array
    {
        $commands = [];

        if (!isset($data['commands'])) {
            return $commands;
        }

        foreach ($data['commands'] as $command) {
            $commands[] = new Command(
                $command['command'],
                $command['type'],
                false,
            );
        }

        return $commands;
    }

    /**
     * @param array $data
     *
     * @return array<\SprykerSdk\SdkContracts\Entity\FileInterface>
     */
    protected function buildFiles(array $data): array
    {
        $files = [];

        if (!isset($data['files'])) {
            return $files;
        }

        foreach ($data['files'] as $file) {
            $files[] = new File(
                $file['path'],
                $file['content'],
            );
        }

        return $files;
    }

    /**
     * @param array $taskData
     * @param array $taskListData
     * @param array $tags
     *
     * @return \SprykerSdk\SdkContracts\Entity\Lifecycle\TaskLifecycleInterface
     */
    protected function buildLifecycle(array $taskData, array $taskListData, array $tags = []): TaskLifecycleInterface
    {
        return new Lifecycle(
            $this->buildInitializedEventData($taskData, $taskListData, $tags),
            $this->buildUpdatedEventData($taskData, $taskListData, $tags),
            $this->buildRemovedEventData($taskData, $taskListData, $tags),
        );
    }

    /**
     * @param array $taskData
     * @param array $taskListData
     * @param array $tags
     *
     * @return \SprykerSdk\Sdk\Core\Domain\Entity\Lifecycle\InitializedEventData
     */
    protected function buildInitializedEventData(array $taskData, array $taskListData, array $tags = []): InitializedEventData
    {
        if (!isset($taskData['lifecycle']['INITIALIZED'])) {
            return new InitializedEventData();
        }

        $eventData = $taskData['lifecycle']['INITIALIZED'];

        return new InitializedEventData(
            $this->buildLifecycleCommands($eventData),
            $this->buildPlaceholders($eventData, $taskListData, $tags),
            $this->buildFiles($eventData),
        );
    }

    /**
     * @param array $taskData
     * @param array $taskListData
     * @param array $tags
     *
     * @return \SprykerSdk\Sdk\Core\Domain\Entity\Lifecycle\RemovedEventData
     */
    protected function buildRemovedEventData(array $taskData, array $taskListData, array $tags = []): RemovedEventData
    {
        if (!isset($taskData['lifecycle']['REMOVED'])) {
            return new RemovedEventData();
        }

        $eventData = $taskData['lifecycle']['REMOVED'];

        return new RemovedEventData(
            $this->buildLifecycleCommands($eventData),
            $this->buildPlaceholders($eventData, $taskListData, $tags),
            $this->buildFiles($eventData),
        );
    }

    /**
     * @param array $taskData
     * @param array $taskListData
     * @param array $tags
     *
     * @return \SprykerSdk\Sdk\Core\Domain\Entity\Lifecycle\UpdatedEventData
     */
    protected function buildUpdatedEventData(array $taskData, array $taskListData, array $tags = []): UpdatedEventData
    {
        if (!isset($taskData['lifecycle']['UPDATED'])) {
            return new UpdatedEventData();
        }

        $eventData = $taskData['lifecycle']['UPDATED'];

        return new UpdatedEventData(
            $this->buildLifecycleCommands($eventData),
            $this->buildPlaceholders($eventData, $taskListData, $tags),
            $this->buildFiles($eventData),
        );
    }

    /**
     * @param array $taskData
     * @param array $taskListData
     * @param array $tags
     *
     * @return \SprykerSdk\SdkContracts\Entity\TaskInterface
     */
    protected function buildTask(array $taskData, array $taskListData, array $tags = []): TaskInterface
    {
        $placeholders = $this->buildPlaceholders($taskData, $taskListData, $tags);
        $commands = $this->buildCommands($taskData, $taskListData, $tags);
        $lifecycle = $this->buildLifecycle($taskData, $taskListData, $tags);

        return new Task(
            $taskData['id'],
            $taskData['short_description'],
            $commands,
            $lifecycle,
            $taskData['version'],
            $placeholders,
            $taskData['help'] ?? null,
            $taskData['successor'] ?? null,
            $taskData['deprecated'] ?? false,
            $taskData['stage'] ?? ContextInterface::DEFAULT_STAGE,
            [],
            !empty($taskData['optional']),
        );
    }

    /**
     * @param array $taskData
     * @param array $taskListData
     * @param array<string, \SprykerSdk\SdkContracts\Entity\TaskInterface> $tasks
     * @param array $tags
     *
     * @return \SprykerSdk\SdkContracts\Entity\TaskInterface
     */
    protected function buildTaskSet(array $taskData, array $taskListData, array $tasks, array $tags = []): TaskInterface
    {
        $task = $this->buildTask($taskData, $taskListData, $tags);

        if (!isset($taskData['tasks'])) {
            return $task;
        }

        foreach ($taskData['tasks'] as $taggedTaskData) {
            $taggedTask = $tasks[$taggedTaskData['id']] ?? null;

            if (!$taggedTask instanceof TaggedTaskInterface) {
                continue;
            }

            $taggedTask->setTags($taggedTaskData['tags'] ?? []);
        }

        return $task;
    }
}
