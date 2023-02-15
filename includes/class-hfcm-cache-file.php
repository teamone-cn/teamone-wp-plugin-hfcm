<?php
/**
 * Author: Teamone
 * Date: 2023/1/9
 * Action: Cache JSON files and generate log files
 */

define('Cache_File_Path',plugin_dir_path(__DIR__).'cache/'.$_SERVER['SERVER_NAME'].'/');
define('Logo_File_Path',plugin_dir_path(__DIR__).'log/');

class Teamone_Hfcm_Cache_File{

    
    function __construct(){

    }

    //创建缓存json文件写入内容
    public static function set_json($rekey,$json){
        
        if(!empty($json)){
            if(file_exists(Cache_File_Path.$rekey.'.json')){
                self::get_json($rekey);
            }else{
                try{

                    if(!file_exists(Cache_File_Path)){
                        mkdir(Cache_File_Path);
                    }
                    

                    $cahe_file = fopen(Cache_File_Path.$rekey.'.json','w');
                    
                    $fwrite_res = fwrite($cahe_file, $json);
                    
                    @chmod(Cache_File_Path.$rekey.'.json', 0755);
        
                    fclose($cahe_file);
                    }catch (Exception $exception){
                        self::flie_log($rekey.'.json'.'创建写入失败'.$exception);
                        self::return_msg(0,'写入失败');
                }
        
                // self::flie_log($rekey.'.json'.'创建写入成功');
                self::return_msg(1,'创建写入成功');	
            }
        }	
    }

    //获取缓存json文件内容
    public static function get_json($rekey){
        try{
            if(file_exists(Cache_File_Path.$rekey.'.json')){
                
                $cache_str = @file_get_contents(Cache_File_Path.$rekey.'.json');
    
                $cache_json = json_decode($cache_str);
                $res = $cache_json;
                
                // self::flie_log($rekey.'.json'.'获取数据成功');
                return  $cache_json;
                // self::return_msg(1,'获取数据成功',$cache_json);
            }
        }catch(Exception $exception){
            self::flie_log($rekey.'.json'.'获取数据错误:'.$exception);
            self::return_msg(0,'文件不存在');
        }

        
    }

    //删除缓存json文件
    public static function del_json_file($rekey){

        try{
            if(file_exists(Cache_File_Path.$rekey.'.json')){
                $status=unlink(Cache_File_Path.$rekey.'.json');    
                if($status){
                    // self::flie_log($status.'删除缓存文件成功');  
                    self::return_msg(1,'删除缓存文件成功');   
                }else{
                    self::flie_log($status.'删除缓存文件失败'); 
                    self::return_msg(0,'删除缓存文件失败');
                }
            }  
        }catch(Exception $exception){
            self::flie_log($status.'删除缓存文件失败'.$exception);
        }
              
    }

    //统一返回数据结构
    public static function return_msg($status_code = 0,$msg = '',$data=''){
        $res = array(
            'code'=>$status_code,
            '$msg'=>$msg,
            'data'=>$data
        );

        return $res;

    }

    //日志文件
    public static function flie_log($log_msg,$msg_type ='cache'){

        $log_set = defined('HFCM_LOG') ? HFCM_LOG : get_option('hfcm_debug_log');

        if($log_set){

            $msg_type = $msg_type == 'cache' ? 'cache':'redis';

            $log_msg = date('Y-m-d H:i:s',current_time('timestamp')).' -- '.$log_msg.PHP_EOL;

            $log_file = Logo_File_Path.$msg_type.'.log';

            error_log($log_msg,3,$log_file);
        }
    }

    
}

