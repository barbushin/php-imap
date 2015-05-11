<?php namespace PhpImap;

spl_autoload_register(function ($class) {
	if(strpos($class, __NAMESPACE__) === 0) {
		/** @noinspection PhpIncludeInspection */
		require_once(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $class) . '.php');
	}
});