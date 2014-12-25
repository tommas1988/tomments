<?php
namespace TUtils;

use XHProfRuns_Default;
use LogicException;
use InvalidArgumentException;

class Profiler
{
    /**
     * xhprof path
     * @var string
     */
    private static $xhprofPath;

    /**
     * Whether profile randomly
     * @var bool
     */
    private static $randProf;

    /**
     * Profiler instance
     * @var self
     */
    private static $instance;

    /**
     * Is on flag
     * @var bool
     */
    private $isOn;

    /**
     * The current profile namespace
     */
    private $namespace;

    /**
     * The array of measurement data
     * @var array
     */
    private $dataset;

    /**
     * Get profiler instance
     *
     * @return self
     */
    public static function getInstance()
    {
        if (!self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Set profiler options
     *
     * @param array options
     */
    public static function setOptions(array $options)
    {
        if (isset($options['xhprof_path'])) {
            if (false === ($path = realpath($options['xhprof_path']))) {
                throw new InvalidArgumentException(sprintf(
                    'The path %s dose not exist', $options['xhprof_path']));
            }

            self::$xhprofPath = $path;
        }

        if (isset($options['rand_prof'])) {
            self::$randProf = (bool) $options['rand_prof'];
        }
    }

    /**
     * Constructor
     */
    private function __construct()
    {
        spl_autoload_register(array($this, 'xhprofAutoloader'));
    }

    /**
     * xhprof autoloader
     *
     * @param string className
     */
    public function xhprofAutoloader($className)
    {
        if (false === strpos($className, 'XHProf')) {
            return;
        }

        include self::$xhprofPath . '/xhprof_lib/utils/xhprof_lib.php';
        include self::$xhprofPath . '/xhprof_lib/utils/xhprof_runs.php';
    }

    /**
     * Start collection data
     *
     * @param string namespace The profile namespace
     */
    public function start($namespace)
    {
        if ($this->isOn) {
            throw new LogicException('Processing conficts');
        }

        if (self::$randProf && mt_rand(1, 10000) != 1) {
            return;
        }

        xhprof_enable(\XHPROF_FLAGS_MEMORY, array(
            'ignored_functions' => array(
                'call_user_func',
                'call_user_func_array',
            ),
        ));

        register_shutdown_function(array($this, 'save'), $namespace);

        $this->namespace = $namespace;
        $this->isOn      = true;
    }

    /**
     * Stop collecting data if profiler is running
     */
    public function stop()
    {
        if (!$this->isOn) {
            return;
        }

        $this->dataset[$this->namespace] = xhprof_disable();
        $this->isOn                      = false;
    }

    /**
     * Save the collecting data if profiler is running
     *
     * @param string namespace The profile namespace
     */
    public function save($namespace)
    {
        if (!isset($this->dataset[$namespace])) {
            throw new LogicException(sprintf('No %s profile', $namespace));
        }

        $runId = date('YmdHms');
        $runs  = new XHProfRuns_Default();

        $runs->save_run($this->dataset[$namespace], $namespace, $runId);
    }
}
