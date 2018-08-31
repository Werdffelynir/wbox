<?php


class SRouterException extends RuntimeException { public $message; }


class SRouter
{
    private $port;
    private $protocol;
    private $domain;
    private $basePath;
    private $baseScriptName;
    private $requestUri;
    private $requestMethod;
    private $currentRequest;
    private $currentGetParams;
    private $routerResult;
    private $routerErrors;
    private $forceRun = false;
    private $regReplaces = [
        ':n!' => '\d+',
        ':s!' => '[a-zA-Z]+',
        ':a!' => '\w+',
        ':p!' => '[\w\?\&\=\-\%\.\+]+',
        ':*!' => '[\w\?\&\=\-\%\.\+\/]+',
        ':n?' => '\d{0,}',
        ':s?' => '[a-zA-Z]{0,}',
        ':a?' => '\w{0,}',
        ':p?' => '[\w\?\&\=\-\%\.\+\{\}]{0,}',
        ':*?' => '[\w\?\&\=\-\%\.\+\{\}\/]{0,}',
        '/' => '\/',
        '<' => '?<',
        ').'=> ')\.',
    ];

    /**
     * Can accept params
     * <pre>
     * [
     *      // Базовые настройки:
     *      // Настройки доменного имени если оно отличается от реального
     *      // например: 'mysite.com'
     *      'domain'=> '',
     *      // Базовый путь к роутеру, если индекс находится не в корене, нужно указать путь.
     *      // например если сайт лежит в под-директории (www/mysite.com/some/index.php): /some/
     *      'base_path'=> '',
     *      // Другие параметры настройки,
     *      'request_uri'=>'string',
     *      'request_method'=>'string',
     *      'base_script_name'=>'string'
     * ];
     * </pre>
     * @param array $params
     */
    public function __construct(array $params=[])
    {
        $defConfig = [

        ];

        $this->domain = (empty($params['domain'])) ?  $_SERVER['HTTP_HOST'] : $params['domain'];
        $this->basePath = (empty($params['base_path'])) ? '/' : '/'.trim($params['base_path'],'/').'/';
        $this->requestMethod = (empty($params['request_method'])) ?  strtoupper($_SERVER['REQUEST_METHOD']) : $params['request_method'];
        $this->baseScriptName = (empty($params['base_script_name'])) ? pathinfo($_SERVER['SCRIPT_FILENAME'])['basename'] : $params['base_script_name'];

        // assign property Request Uri
        $requestUri = (empty($params['request_uri'])) ?  $_SERVER['REQUEST_URI'] : $params['request_uri'];
        $replaces = [trim($this->basePath,'/'), $this->baseScriptName];

        $this->requestUri = trim(str_ireplace($replaces,'',urldecode($requestUri)),'/');
        $this->port = $_SERVER['SERVER_PORT'];
        $this->protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == "on") ? 'https':'http';
        $this->determineRequestParams();

    }

    /**
     * Supported request methods
     *
     * @param string|array  $condition      uri path rules
     * @param callable      $callback       callback function
     * @param array         $callbackParams callback function parameters
     */
    public function post($condition,$callback,array $callbackParams=[]){
        $this->map('POST',$condition,$callback,$callbackParams);
    }

    /**
     * @param string|array  $condition      uri path rules
     * @param callable      $callback       callback function
     * @param array         $callbackParams callback function parameters
     */
    public function get($condition,$callback,array $callbackParams=[]){
        $this->map('GET',$condition,$callback,$callbackParams);
    }

    /**
     * @param string|array  $condition      uri path rules
     * @param callable      $callback       callback function
     * @param array         $callbackParams callback function parameters
     */
    public function put($condition,$callback,array $callbackParams=[]){
        $this->map('PUT',$condition,$callback,$callbackParams);
    }

    /**
     * @param string|array  $condition      uri path rules
     * @param callable      $callback       callback function
     * @param array         $callbackParams callback function parameters
     */
    public function delete($condition,$callback,array $callbackParams=[]){
        $this->map('DELETE',$condition,$callback,$callbackParams);
    }

    /**
     * @param string|array  $condition      uri path rules
     * @param callable      $callback       callback function
     * @param array         $callbackParams callback function parameters
     */
    public function options($condition,$callback,array $callbackParams=[]){
        $this->map('OPTIONS',$condition,$callback,$callbackParams);
    }

    /**
     * @param string|array  $condition      uri path rules
     * @param callable      $callback       callback function
     * @param array         $callbackParams callback function parameters
     */
    public function xhr($condition,$callback,array $callbackParams=[]){
        $this->map('XHR',$condition,$callback,$callbackParams);
    }

    /**
     * @param string        $method
     * @param string|array  $condition
     * @param callable      $callback
     * @param array         $addedCallbackParams
     */
    public function map($method,$condition,$callback,array $addedCallbackParams=[])
    {
        if(strpos($method, '|'))
        {
            $methods = explode('|', $method);
            foreach ($methods as $mth) {
                /*
                $mth = trim(strtolower($mth));
                if(method_exists($this,$mth))
                    $this->$mth($condition,$callback,$addedCallbackParams);
                */
                $this->map(strtoupper(trim($mth)),$condition,$callback,$addedCallbackParams);
            }
        }
        else
        {
            if(is_array($condition)){
                foreach ($condition as $one) {
                    $this->runProcessing(strtoupper($method),$one,$callback,$addedCallbackParams);
                }
            }else{
                $this->runProcessing(strtoupper($method),$condition,$callback,$addedCallbackParams);
            }
        }
    }
    /**
     * @param string        $method
     * @param string|array  $condition
     * @param callable      $callback
     * @param array         $addedCallbackParams
     */
    private function runProcessing($method,$condition,$callback,$addedCallbackParams){
        // stop processing if you already have the result
        if(!empty($this->routerResult))
            goto mapEnd;

        // if request method is an indication
        if($this->requestMethod==$method || ($method == 'XHR' && $this->isXMLHTTPRequest()) ) {

            $callableParams = $this->conditionMatch($condition);
            if($callableParams) {
                $callbackParams = array_merge($addedCallbackParams,$callableParams['numberParams']);
                $this->routerResult = [
                    'method'    => $method,
                    'callback'  => $callback,
                    'params'    => $callbackParams,
                    'paramsGet' => $this->currentGetParams,
                ];
                if($this->forceRun) {
                    if(is_callable($callback))
                        call_user_func_array($callback, (array) $callbackParams);
                    else
                        $this->routerErrors .= __LINE__."line. Error is no a callable $callback \n";
                }
            }
        }
        mapEnd:
    }
    /**
     * <pre>
     * Examples: $condition
     * user/(<name>:a?)
     * user/(<name>:a!)
     * user/(<id>:n!)
     * user/(<name>:a!)/(<id>:n!)'
     * page/(:p!)/(:p!)/(:p?)
     * page/(:*!) all valid symbols and separator / to
     * page/(:*!)/(:*!)/(:*!) WRONG !!!
     * </pre>
     * @param $condition
     * @return array 'namedParams'=> 'numberParams'=>
     */
    private function conditionMatch($condition)
    {
        $hewLimiter = true;
        if(strpos($condition,':*') !== false)
            $hewLimiter = false;
        # first handle
        $parts = explode('/', trim($condition,'/'));
        $toMap = '';
        foreach ($parts as $part) {
            $position = strpos($part, ":");
            if(strpos($part, "<") !== false || $position !== false){
                $part = (substr($part, $position+2, 1) == '?') ? "?($part)" : "($part)";

            }
            $toMap .= '/'.$part;
        }
        # second handle
        $toMap = strtr($toMap, $this->regReplaces);
        # third handle, params joins or if match success return empty params
        if(preg_match("|^{$toMap}$|i", $this->currentRequest, $result)){
            $namedParams = [];
            $numberParams = [];
            if(count($result)>1){
                array_shift($result);
                if($hewLimiter) {
                    foreach ($result as $resultKey=>$resultVal) {
                        if(is_string($resultKey))
                            $namedParams[$resultKey] = $resultVal;
                        else
                            $numberParams[] = $resultVal;
                    }
                }else{
                    $numberParams = explode('/',$result[0]);
                }
            }
            return [
                'namedParams' => $namedParams,
                'numberParams'=> $numberParams
            ];
        }
        return false;
    }

    private function determineRequestParams()
    {
        if(empty($this->requestUri))
            $case = '/';
        else
            $case = $this->requestUri;

        $params = null;
        if($this->requestMethod=='GET'){
            if(!empty($_GET)){
                $get = explode('?',$case);
                if(count($get) > 1) {
                    $case = $get[0];
                    parse_str($get[1], $params);
                } else
                    $case = is_array($get) ? join('/',$get) : $get ;
            }else{
                $params = null;
            }
        }else
            parse_str(file_get_contents('php://input'), $params);

        $this->currentRequest = '/'.trim($case,'/');
        $this->currentGetParams = $params;
    }

    /**
     * Return current request port
     * @return mixed
     */
    public function getPort(){
        return $this->port;
    }

    /**
     * Return current request protocol, http or https
     * @return mixed
     */
    public function getProtocol(){
        return $this->protocol;
    }

    /**
     * Return and or create base relative url
     * @param string $link
     * @return string
     */
    public function getUrl($link = ''){
        return $this->basePath . $link;
    }

    /**
     * Return and or create base absolute url
     * @param string $link
     * @return string
     */
    public function getFullUrl($link = ''){
        return $this->protocol.'://'.$this->domain.$this->basePath . $link;
    }

    /**
     * Return current domain name
     * @return mixed
     */
    public function getDomain(){
        return $this->domain;
    }

    /**
     * Returns an array, or null if no one rule does not fit
     *
     * @return array|null
     */
    public function getRouterResult() {
        return $this->routerResult;
    }

    public function getParams($name=null) {
        $_params  = $this->routerResult['params'];
        $_paramsGet = $this->routerResult['paramsGet'];
        $params = array_merge($_paramsGet,$_params);
        if($name !== null){
            if(isset($params[$name]))
                return $params[$name];
            else
                return null;
        }else{
            return $params;
        }
    }

    /**
     * Return router errors for current request
     * @return mixed
     */
    public function getRouterErrors() {
        return $this->routerErrors;
    }

    /**
     * Executed $callback with $callbackParams, when no one rule does not fit
     *
     * @param callable $callback
     * @param array $callbackParams
     */
    public function notFount(callable $callback,array $callbackParams=[])
    {
        if(is_null($this->getRouterResult()) && is_null($this->getRouterErrors())){
            if(is_callable($callback))
                call_user_func_array($callback, (array) $callbackParams);
            else
                $this->routerErrors .= __LINE__."line. Error is no a callable $callback \n";
        }
    }

    /**
     * Executed immediately when finding matching, and skip the other rules
     * @param bool|true $force.
     */
    public function forceRun($force=true) {
        $this->forceRun = (bool) $force;
    }

    /**
     * Start implementation of the rules of the first found, after checking all the rules
     */
    public function run() {
        if(is_callable($this->routerResult['callback']))
            call_user_func_array($this->routerResult['callback'], (array) $this->routerResult['params']);
        else
            $this->routerErrors .= __LINE__."line. Error is no a callable $this->routerResult['callback'] \n";
    }

    /**
     * Кодирукт строку как часть URL
     * @param $link
     * @param bool|false $encodeSeparators
     * @return mixed|string
     */
    public function encodeLink($link, $encodeSeparators = false)
    {
        if($encodeSeparators)
            return urlencode($link);
        $link = urlencode(str_replace(['&','='],['SEPARATOR_AND','SEPARATOR_TO'],$link));
        return str_replace(['SEPARATOR_AND','SEPARATOR_TO'],['&','='],$link);
    }

    /**
     * Декодирукт строку с URL формата
     * @param $link
     * @return string
     */
    public function decodeLink($link)
    {
        return urldecode($link);
    }

    /**
     * Проверка является ли запрос асинхронным. проверяет наличие заголовка 'HTTP_X_REQUESTED_WITH'
     *
     * @return bool
     */
    public function isXMLHTTPRequest() {
        return (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) ==
            'xmlhttprequest') || isset($_GET['ajax']);
    }


}
