<?php

/**
 * Copyright © 2019-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace SprykerSdk\Sdk\Core\Appplication\Dependency;

use SprykerSdk\Sdk\Core\Appplication\Exception\MissingSettingException;
use SprykerSdk\Sdk\Core\Appplication\Exception\MissingValueException;

abstract class AbstractValueResolver implements ValueResolverInterface
{
    /**
     * @var \SprykerSdk\Sdk\Core\Appplication\Dependency\ValueReceiverInterface
     */
    protected ValueReceiverInterface $valueReceiver;

    /**
     * @param \SprykerSdk\Sdk\Core\Appplication\Dependency\ValueReceiverInterface $valueReceiver
     */
    public function __construct(ValueReceiverInterface $valueReceiver)
    {
        $this->valueReceiver = $valueReceiver;
    }

    /**
     * @param array<string, \SprykerSdk\Sdk\Infrastructure\Entity\Setting> $settingValues
     * @param bool|false $optional
     *
     * @throws \SprykerSdk\Sdk\Core\Appplication\Exception\MissingSettingException
     *
     * @return mixed
     */
    public function getValue(array $settingValues, bool $optional = false): mixed
    {
        if ($this->valueReceiver->has($this->getValueName())) {
            return $this->valueReceiver->get($this->getValueName());
        }

        $requiredSettings = array_intersect(array_keys($settingValues), $this->getRequiredSettingPaths());

        if (count($requiredSettings) !== count($this->getRequiredSettingPaths())) {
            throw new MissingSettingException(
                'Required settings are missing: ' . implode(', ', array_diff($this->getRequiredSettingPaths(), $settingValues)),
            );
        }
        $defaultValue = $this->getDefaultValue();

        if ($defaultValue === null) {
            try {
                $defaultValue = $this->getValueFromSettings($settingValues);
            } catch (MissingValueException) {
                $defaultValue = null;
            }
        }

        if (!$optional) {
            $defaultValue = $this->valueReceiver->receiveValue($this->getDescription(), $defaultValue, $this->getType());
        }

        return $defaultValue;
    }

    /**
     * @return string
     */
    protected function getValueName(): string
    {
        if ($this->getAlias()) {
            return $this->getAlias();
        }

        return $this->getId();
    }

    /**
     * @return array<string>
     */
    abstract protected function getRequiredSettingPaths(): array;

    /**
     * @param array<string, mixed> $settingValues
     *
     * @throws \SprykerSdk\Sdk\Core\Appplication\Exception\MissingValueException
     *
     * @return mixed
     */
    abstract protected function getValueFromSettings(array $settingValues): mixed;
}
