<?php

abstract class SigSecurityTestUtils
{

    /**
     * Perform a test with some specific configuration which should not affect other tests.
     *
     * @param array $configMap
     * @param Callable $testCallback
     */
    public static function testWithConfig($configMap, $testCallback)
    {
        $originalConfigValues = array();
        // Set new config values.
        foreach ($configMap as $class => $config) {
            foreach ($config as $key => $value) {
                $originalConfigValues[$class][$key] = Config::inst()->get($class, $key);
                Config::inst()->update($class, $key, $value);
            }
        }

        // Perform test with specific configuration.
        $testCallback();

        // Restore original config values.
        foreach ($originalConfigValues as $class => $config) {
            foreach ($config as $key => $value) {
                Config::inst()->update($class, $key, $value);
            }
        }
    }

    public static function array_change_key_case_deep(array $input, $case = null)
    {
        foreach ($input as $header => &$value) {
            if (is_array($value)) {
                $value = self::array_change_key_case_deep($value, $case);
            }
        }
        return array_change_key_case($input, $case);
    }
}
