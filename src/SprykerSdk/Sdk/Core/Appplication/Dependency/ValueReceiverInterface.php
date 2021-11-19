<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace SprykerSdk\Sdk\Core\Appplication\Dependency;

interface ValueReceiverInterface
{
    /**
     * @param string $key
     *
     * @return bool
     */
    public function hasOption(string $key): bool;

    /**
     * @param string $key
     *
     * @return mixed
     */
    public function getOption(string $key): mixed;

    /**
     * @param string $description
     * @param mixed $defaultValue
     * @param string $type
     *
     * @return mixed
     */
    public function askValue(string $description, mixed $defaultValue, string $type): mixed;
}
