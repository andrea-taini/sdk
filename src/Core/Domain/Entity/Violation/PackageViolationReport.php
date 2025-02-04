<?php

/**
 * Copyright © 2019-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace SprykerSdk\Sdk\Core\Domain\Entity\Violation;

use SprykerSdk\SdkContracts\Violation\PackageViolationReportInterface;

class PackageViolationReport implements PackageViolationReportInterface
{
    protected string $package;

    protected string $path;

    protected array $violations;

    protected array $fileViolations;

    /**
     * @param string $package
     * @param string $path
     * @param array<\SprykerSdk\SdkContracts\Violation\ViolationReportConverterInterface> $violations
     * @param array<string, array<\SprykerSdk\SdkContracts\Violation\ViolationReportConverterInterface>> $fileViolations
     */
    public function __construct(string $package, string $path, array $violations = [], array $fileViolations = [])
    {
        $this->package = $package;
        $this->path = $path;
        $this->violations = $violations;
        $this->fileViolations = $fileViolations;
    }

    /**
     * @return string
     */
    public function getPackage(): string
    {
        return $this->package;
    }

    /**
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * @return array<\SprykerSdk\SdkContracts\Violation\ViolationReportConverterInterface>
     */
    public function getViolations(): array
    {
        return $this->violations;
    }

    /**
     * @return array<string, array<\SprykerSdk\SdkContracts\Violation\ViolationReportConverterInterface>>
     */
    public function getFileViolations(): array
    {
        return $this->fileViolations;
    }
}
