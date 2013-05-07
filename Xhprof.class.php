<?php
class WF_Xhprof {

    /**
     * enable xhprof 
     *
     * @param int $flag XHPROF_FLAGS_MEMORY, XHPROF_FLAGS_CPU, XHPROF_FLAGS_NO_BUILTINS
     */
    static function Enable($flag = null) {
        xhprof_enable($flag);
    }

    static function Disable() {
        return xhprof_disable();
    }

    /**
     * save run data
     *
     * @param array $run run data
     * @param string $namespace save namespace
     * @return string id
     */
    static function Save($run, $namespace, $root='') {
        if ($root){
            $root = rtrim($root, '/') . '/';
        }
        include_once $root . "xhprof_lib/utils/xhprof_lib.php";
        include_once $root . "xhprof_lib/utils/xhprof_runs.php";

        $xhprof = new XHProfRuns_Default;

        return $xhprof->save_run($run, $namespace);
    }

    /**
     * profile run data
     *
     * @param string $namespace save namespace
     */
    static function Profile($namespace, $rate=1) {
        $rate = intval($rate);
        $enable = $rate <= 1 ? true : rand(1, $rate) == 1;
        if ($enable) {
            self::Enable(XHPROF_FLAGS_CPU);
            $class = __CLASS__;
            $func = create_function('$namespace', '$run = '.$class.'::Disable(); '.$class.'::Save($run, $namespace);');
            register_shutdown_function($func, $namespace);
        }
    }

}
