<?php
/**
 * Scavix Web Development Framework
 *
 * Copyright (c) since 2024 Scavix Software GmbH & Co. KG
 *
 * This library is free software; you can redistribute it
 * and/or modify it under the terms of the GNU Lesser General
 * Public License as published by the Free Software Foundation;
 * either version 3 of the License, or (at your option) any
 * later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library. If not, see <http://www.gnu.org/licenses/>
 *
 * @author Scavix Software GmbH & Co. KG https://www.scavix.com <info@scavix.com>
 * @copyright since 2024 Scavix Software GmbH & Co. KG
 * @license http://www.opensource.org/licenses/lgpl-license.php LGPL
 */
use ScavixWDF\WdfException;

ScavixWDF\Wdf::RegisterPackage('ai', 'ai_init');

/**
 * Initializes the AI module
 * @return void
 */
function ai_init()
{
    if( !class_exists("\\Google\\Cloud\\AIPlatform\\V1\\Client\\PredictionServiceClient") )
        WdfException::Raise("Missing Google AI client, see https://github.com/googleapis/google-cloud-php-ai-platform");

    classpath_add(__DIR__ . '/src', true, 'system');
}

/**
 * Creates a AI based prediction.
 *
 * @param mixed $prompt Prompt for the AI engine
 * @param mixed $options Optional options
 * @param mixed $cache if true, cache the result for a while (default: false)
 * @return mixed
 */
function ai_predict($prompt, $options = [], $cache = false)
{
    log_warn("Deprected use of '" . __FUNCTION__ . "': use 'WdfAiHandler::Predict' instead.");
    return WdfAiHandler::Predict($prompt, $options, $cache);
}
