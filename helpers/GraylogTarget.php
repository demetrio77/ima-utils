<?php

namespace demetrio77\imautils\helpers;

use Yii;
use yii\log\Target;
use yii\log\Logger;
use Gelf\Transport\UdpTransport;
use Gelf\Publisher;
use Psr\Log\LogLevel;
use yii\helpers\ArrayHelper;
use yii\helpers\VarDumper;
use Gelf\Message;

class GraylogTarget extends Target
{
	/**
	 * @var string Graylog2 host
	 */
	public $host = '127.0.0.1';
	
	/**
	 * @var integer Graylog2 port
	 */
	public $port = 12201;
	
	/**
	 * @var string default facility name
	 */
	public $facility = 'cherinfo';
	
	private $_levels = [
		Logger::LEVEL_TRACE => LogLevel::DEBUG,
		Logger::LEVEL_PROFILE_BEGIN => LogLevel::DEBUG,
		Logger::LEVEL_PROFILE => LogLevel::DEBUG,
		Logger::LEVEL_PROFILE_END => LogLevel::DEBUG,
		Logger::LEVEL_INFO => LogLevel::INFO,
		Logger::LEVEL_WARNING => LogLevel::WARNING,
		Logger::LEVEL_ERROR => LogLevel::ERROR,
	];
	
	/**
	 * Sends log messages to Graylog2 input
	 */
	public function export()
	{
		$transport = new UdpTransport($this->host, $this->port);
		$publisher = new Publisher($transport);
		
		$Request = Yii::$app->request;

		if ($Request instanceof yii\web\Request) {
			$script_data = [
				'http_request' => $Request->url,
				'http_method' => $Request->method,
				'vhost' => $Request->serverName,
				'application_name' => 'yii_error_'.implode('_', explode('.', $Request->serverName)),
				'source_ip' => $Request->userIP,
				'http_status' => Yii::$app->response->statusCode
			];
		}
		elseif ($Request instanceof yii\console\Request) {
			$script_data = [
				'http_request' => $Request->scriptFile,
				'http_method' => '',
				'vhost' => '',
				'application_name' => 'yii_error_cli_cherinfo_ru',
				'source_ip' => '',
				'http_status' => Yii::$app->response->exitStatus
			];
		}
		
		foreach ($this->messages as $i => $message) {
			$additional = $script_data;
			$file = '';
			$line = 0;
			$short = null;
			$full = null;
			
			list($text, $level, $category, $timestamp) = $message;
			
			$level = ArrayHelper::getValue($this->_levels, $level, LogLevel::INFO);
			
			if (is_string($text)) {
				$short = $text;
			}
			elseif ($text instanceof \Exception) {
				$short = 'Exception ' . get_class($text) . ': ' . $text->getMessage();
				$full = (string) $text;
				$line = $text->getLine();
				$file = $text->getFile();
			} 
			else {
				$short = ArrayHelper::remove($text, 'short');
				$full = ArrayHelper::remove($text, 'full');
				$add = ArrayHelper::remove($text, 'add');
				if ($short !== null) {
					$full = VarDumper::dumpAsString($text);
				} 
				else {
					$short = VarDumper::dumpAsString($text);
				}
				if ($full !== null) {
					$full = VarDumper::dumpAsString($full);
				}
				if (is_array($add)) {
					foreach ($add as $key => $val) {
						if (is_string($key)) {
							if (!is_string($val)) {
								$val = VarDumper::dumpAsString($val);
							}
							$additional[$key] =  $val;
						}
					}
				}
			}
			
			if (isset($message[4]) && is_array($message[4])) {
				$traces = [];
				foreach ($message[4] as $index => $trace) {
					$traces[] = "{$trace['file']}:{$trace['line']}";
					if ($index === 0) {
						$file = $trace['file'];
						$line = $trace['line'];
					}
				}
				$additional['trace'] = implode("\n", $traces);
			}
			
			$Msg = new Message();
			
			$Msg->setLevel($level)
				->setTimestamp($timestamp)
				->setFacility($this->facility)
				->setFile($file)
				->setLine($line)
				->setAdditional('category', $category)
				->setShortMessage($short)
				->setFullMessage($full);
			
			foreach ($additional as $key => $value) {
				$Msg->setAdditional($key, $value);
			}
			
			$publisher->publish($Msg);
		}
	}
}