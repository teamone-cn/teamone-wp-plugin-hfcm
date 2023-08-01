<?php
/**
 * Author: Teamone
 * Date: 2022/12/28
 * Action: Added redis cache layer operation
 */
class Teamone_Hfcm_Redis
{
	//
    
    private static $redis;
    //redis连接
    private $conn;
    //判断是否redis开启成功
    public $open_redis = false;
    public static $hfcm_settable = "team_one_hfcm_scripts_set";

    //私有构造函数
    function __construct()
    {
    	//连接本地redis
        $host = defined('HFCM_REDIS_HOST') ? HFCM_REDIS_HOST :  get_option('hfcm_redis_host');
        $port = defined('HFCM_REDIS_PORT') ? HFCM_REDIS_PORT :  get_option('hfcm_redis_port');

        
        if($host && $port){
            $this->conn = new Redis();
            $file_cache = new Teamone_Hfcm_Cache_File();
            try{
                $this->conn -> connect($host,$port);
    
                $password = defined('HFCM_REDIS_PASSWORD') ? HFCM_REDIS_PASSWORD :  get_option('hfcm_redis_password');
                
                if($password){
                    $this->conn->auth($password);
                }
                $this->open_redis = true;
            }catch(Exception $e){
                $file_cache::flie_log('Redis:Error_Link:'.$e->getMessage(),'redis');
            }
        }
    }
	//私有克隆方式
    private function __clone()
    {
        // TODO: Implement __clone() method.
    }
	//单一获取对象入口
    public static function get_instance(){
    	//若部位该类对象则实例化对象，若不是则返回已实例化对象
        if (!self::$redis instanceof self){
            self::$redis = new self;
        }
        return self::$redis;
    }

    /*
     * 设置redis
     * value redis设置的值
     * expire redis过期时间
     *
    */
    public function set_redis($key,$value,$expire=0){
        if (is_array($value)){
            $value = json_encode($value);
        }
        return $expire ? $this->conn->set($key,$value,$expire): $this->conn->set($key,$value);
    }

    /*
     * 获取redis
     * key redis的键
     * expire redis的过期时间
     * return array|int|string
     */
    public function get_redis($key,$expire=0){

        //查看生存时间
        $ttl_time = $this->conn->ttl($key);
        if($ttl_time>0 && $ttl_time-20 >0 ){
            $data = $this->conn->get($key);
        }else{
            $data = $this->conn->get($key);
            if(!empty($data)){
                $data  = $this->conn->set($key,$data,$expire);
            }
        }
        return json_decode($data) ? json_decode($data) : $data;
    }

    /*
     * 移除redis
     * key redis的键
     */
    public function del_redis($key){
        return $this->conn->del($key);
    }
    
     /*
     * php list操作
     * lpush操作
     */
    public function lpush($list,$value){
        $value = is_array($value) ? json_encode($value) : $value;
        return $this->conn->lPush($list,$value);
    }

    /*
     * rpush操作
     */
    public function rpush($list,$value){
        $value = is_array($value) ? json_encode($value) : $value;
        return $this->conn->rPush($list,$value);
    }

    /*
     * lrange操作
     */
    public function lrange($key,$start,$end){
        $res = $this->conn->lRange($key,$start,$end);
        foreach ($res as & $k){
            if (json_decode($k)){
                $k = json_decode($k);
            }
        }
        return $res;
    }

    /*
    */
    public static function get_hfcm_set(){

        global $wpdb;
        $table_name      = $wpdb->prefix . self::$hfcm_settable;
        $nnr_set_data = $wpdb->get_row(
            "SELECT * FROM `{$table_name}`"
        ,'ARRAY_A');
        return $nnr_set_data;
    }
}
