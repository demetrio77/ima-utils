<?php

namespace demetrio77\imautils\helpers;

use yii\helpers\ArrayHelper;

class EnvHelper
{	
	public static function get($key, $defaultValue=false)
	{
		return ArrayHelper::getValue($_ENV, $key, $defaultValue);
	}
}