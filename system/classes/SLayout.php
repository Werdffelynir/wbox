<?php

/**
 * Class SLayout.
 * new SLayout( [ 'path' => '', 'template' => '' ] )
 * ->render( $view [, $data [, $callback]] )
 * ::value( $name [, $value] )
 * ->setPosition($position, $view [, $data [, $callback]] )
 * ::outPosition($position [, $returned] )
 * ->outTemplate( [$returned] )
 */
class SLayout
{
    /**
     * Default configuration
     * @var array
     */
    private $defConfig = [
        'path' => 'views',
        'template' => 'layout/template',
    ];

    /**
     * Global template values store
     * @var array
     */
    static private $templateValues = [];

    /**
     * Positions store
     * @var array
     */
    static private $positionsData = [];

    /**
     * SLayout constructor.
     * @param $config
     */
    public function __construct($config = [])
    {
        foreach($this->defConfig as $dcKey => $dcVal) {
            if(!empty($config[$dcKey]))
                $this->defConfig[$dcKey] = $config[$dcKey];
        }
    }

    /**
     * Render partial view, merge variables with (array) $data and pass $data across $callback function
     * @param $view
     * @param array $data
     * @param $callback
     * @return bool|string
     */
    public function render($view, array $data = [], callable $callback = null)
    {
        if($view_path = $this->realFile($view)) {
            if($callback) {
                $callback_result = $callback($data);
                if(is_array($callback_result))
                    $data = $callback_result;
            }
            ob_start();
            extract((array) $data);
            require($view_path);
            return ob_get_clean();
        }else
            return false;
    }


    /**
     * @param $name
     * @param array $args
     * @return mixed
     */
    public function __call($name, array $args)
    {
        var_dump($name);
        if($name === 'value' || $name === 'val' )
            return call_user_func_array( [$this, 'value'], $args );
    }


    private $setStack = [];


    /**
     * Все прикрепляемые свойства к екзкмпляру через сеттер ложатся в отдельный стак
     * @param $name
     * @param $value
     */
    public function __set($name, $value)
    {
        $this->setStack[$name] = $value;
    }


    /**
     * Все прикрепляемые свойства к екзкмпляру ложатся в стак, и получаются через геттер
     * @param $name
     * @return mixed|null
     */
    public function __get($name)
    {
        if(isset($this->setStack[$name]))
            return $this->setStack[$name];
        return null;
    }

    /**
     * Set or get global values
     * @param $name
     * @param null $value
     * @return null
     */
    static public function value($name, $value = null)
    {
        return $value === null
            ? (isset(self::$templateValues[$name]) ? self::$templateValues[$name]:null)
            : self::$templateValues[$name] = $value;
    }

    /**
     * Set $view to $position (look - outPosition())
     * @param $position
     * @param $view
     * @param array $data
     * @param null $callback
     * @return $this
     */
    public function setPosition($position, $view, array $data = [], $callback = null)
    {
        self::$positionsData[$position] = $this->render($view, $data, $callback);
        return $this;
    }

    /**
     * Output view into place,
     * from position store.  (for assign look - setPosition())
     * @param $position
     * @param bool $returned
     * @return null
     */
    static public function outPosition($position, $returned = false)
    {
        $view = isset(self::$positionsData[$position]) ? self::$positionsData[$position] : null;
        if($returned)
            return $view;
        echo $view;
    }

    /**
     * Output Template
     * @param bool $returned
     * @return bool|string
     */
    public function outTemplate($returned = false)
    {
        $template = $this->render($this->defConfig['template'], self::$templateValues);
        if($returned) return $template;
        else
            echo $template;
    }

    /**
     * Return full file path
     * @param $file
     * @return bool|string
     */
    private function realFile($file)
    {
        $file = rtrim(str_replace('\\', '/', $this->defConfig['path']),'/') . '/' . trim($file, '/');

        if(substr($file, -4) !== '.php')
            $file .= '.php';

        return is_file($file) ? $file : false;
    }


}


