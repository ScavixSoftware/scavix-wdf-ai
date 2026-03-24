<?php
/**
 * Scavix Web Development Framework
 *
 * Copyright (c) since 2025 Scavix Software GmbH & Co. KG
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
 * @copyright since 2025 Scavix Software GmbH & Co. KG
 * @license http://www.opensource.org/licenses/lgpl-license.php LGPL
 */

use Google\ApiCore\ApiException;

/**
 * @internal Wrapper class for Google AI
 * @link https://cloud.google.com/php/docs/reference/cloud-ai-platform/latest
 * @suppress PHP0413
 */
class WdfGoogleAIWrapper
{
    private $credentials;
    private $config = [];

    // private static $serializer = false;

    function __construct($config)
    {
        $credentials = false;

        if (is_string($config) && file_exists($config)) // config is credentials file only
        {
            $credentials = json_decode(file_get_contents($config), true);
            putenv('GOOGLE_APPLICATION_CREDENTIALS=' . $config);
            $config = [];
        }

         // config contains credentials file
        if (!$credentials && ($credfile = ($config['configfile'] ?? '')) && file_exists($credfile))
        {
            $credentials = json_decode(file_get_contents($credfile), true);
            putenv('GOOGLE_APPLICATION_CREDENTIALS=' . $credfile);
        }

        // ENV contains credentials file
        if(!$credentials && ($credfile = getenv('GOOGLE_APPLICATION_CREDENTIALS')) && file_exists($credfile))
            $credentials = json_decode(file_get_contents($credfile), true);

        if(!$credentials)
            WdfException::Raise('Google AI module not configured properly');

        $this->credentials = $credentials;
        $this->config = $config;
    }

    /**
     * *
     * @param mixed $prompt
     * @param mixed $options i.e. maxOutputTokens, temperature
     * @param mixed $cache
     * @return mixed
     */
    function Predict($prompt, $options = [], $cache = false)
    {
        if($cache)
        {
            $cache_key = __METHOD__.'-'.sha1($prompt.'-'.json_encode($options));
            if ($v = cache_get($cache_key))
                return $v;
        }

        try
        {
            global $CONFIG;
            if (isset($CONFIG['ai']['google']))
            {
                // log_warn("Depecated use of AI config in \$CONFIG variable. Use 'WdfAIHandler::GoogleAI()' instead");
                $options = array_merge($options, $CONFIG['ai']['google']);
            }
            $options = array_merge($options, $this->config);

            $ret                     = false;
            $model                   = $options['model'] ?? 'text-bison';
            $serviceclientoptions = [
                'apiEndpoint' => $options['apiendpoint'] ?? 'us-central1-aiplatform.googleapis.com'
            ];
            if($loggerclass = ifavail($options, 'psrlogger'))
            {
                // putenv('GOOGLE_SDK_PHP_LOGGING=true');
                try
                {
                    $logger = new $loggerclass();
                    $serviceclientoptions['logger'] = $logger;
                }
                catch (Exception $ex)
                {
                    if (isDev())
                        log_error($ex);
                }
            }
            $predictionServiceClient = new \Google\Cloud\AIPlatform\V1\Client\PredictionServiceClient($serviceclientoptions);
            $gmodel = \Google\Cloud\AIPlatform\V1\Client\PredictionServiceClient::projectLocationPublisherModelName(
                $this->credentials['project_id'],
                $options['location'] ?? 'us-central1',
                'google',
                $model
            );

            // Prepare the request message.
            $contentsParts = [
                'text' => (new \Google\Cloud\AIPlatform\V1\Part())->setText($prompt),
            ];

            $gconfig  = new \Google\Cloud\AIPlatform\V1\GenerationConfig();
            $system_instructions = [];
            foreach($options as $key => $val)
            {
                switch(strtolower($key))
                {
                    case 'temperature':
                        $gconfig->setTemperature($val);
                        break;
                    case 'topk':
                        $gconfig->setTopK($val);
                        break;
                    case 'topp':
                        $gconfig->setTopP($val);
                        break;
                    case 'candidate_count':
                        $gconfig->setCandidateCount($val);
                        break;
                    case 'max_output_tokens':
                        $gconfig->setMaxOutputTokens($val);
                        break;
                    case 'stop_sequences':
                        $gconfig->setStopSequences($val);
                        break;
                    case 'system_instructions':
                    case 'instructions':
                        if(is_array($val))
                            $system_instructions = array_merge($system_instructions, $val);
                        break;
                }
            }

            if ($system_instructions)
                $contentsParts['system_instruction'] = (new \Google\Cloud\AIPlatform\V1\Part())->setText(implode('. ', $system_instructions));

            $content = (new \Google\Cloud\AIPlatform\V1\Content())
                ->setParts($contentsParts)
                ->setRole('user');
            $contents = [$content];
            $request = new \Google\Cloud\AIPlatform\V1\GenerateContentRequest();
            $request
                ->setModel($gmodel)
                ->setContents($contents)
                ->setGenerationConfig($gconfig);

            try {
                $response = $predictionServiceClient->generateContent($request);
                // log_debug($response->serializeToJsonString());
                foreach($response->getCandidates() as $candidate)
                {
                    if ($content = $candidate->getContent())
                    {
                        foreach ($content->getParts() as $part)
                        {
                            if ($ret === false)
                                $ret = '';
                            $ret .= $part->getText();
                        }
                    }
                }
            } catch (\Exception $e) {
                log_error($e);;
            } finally {
                $predictionServiceClient->close();
            }
        }
        catch(ApiException $ex)
        {
            log_error("Exception", $ex);
            if($ex->getStatus() === 'RESOURCE_EXHAUSTED')
                return 'RESOURCE_EXHAUSTED';
        }

        if(isDev())
            log_debug(__METHOD__, $prompt, $options, $ret);

        if ($cache)
        {
            if ($ret)
                cache_set($cache_key, $ret, $cache);
            else
                cache_del($cache_key);
        }

        return $ret;
    }
}
