<?php 

?>

<div class="wrap">
    <h1>Team One HFCM 设置</h1>
    <span>在无法修改配置文件时可设置使用</span>
        <?php $hfcm_form_action = admin_url( 'admin.php?page=team-one-hfcm-set-request' );?>
            <form method="post" action="<?php echo $hfcm_form_action ?>">
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th class="hfcm-th-width">
                                Redis 服务器的IP或主机名 Host:
                            </th>
                            <td>
                                <input type="text" name="host" value="<?php echo esc_attr( get_option('hfcm_redis_host') ); ?>" class="regular-text"/>
                            </td>
                        </tr>
                        <tr>
                            <th class="hfcm-th-width">
                                Redis 端口 Port:
                            </th>
                            <td>
                                <input type="text" name="port" value="<?php echo esc_attr( get_option('hfcm_redis_port') ); ?>"
                                    class="regular-text"/>
                            </td>
                        </tr>
                        <tr>
                            <th class="hfcm-th-width">
                                Redis 密码 PassWord:
                            </th>
                            <td>
                                <input type="text" name="password" value="<?php echo esc_attr( get_option('hfcm_redis_password') ); ?>"
                                    class="regular-text"/>
                            </td>
                        </tr>
                        <tr>
                            <th class="hfcm-th-width">
                                插件错误日志开关:
                            </th>
                            <td>
                                <label>
                                    <select  name="debug_log">
                                        <?php 
                                            var_dump(get_option('hfcm_debug_log'));
                                            if(get_option('hfcm_debug_log')){
                                                $html = '<option value="1" selected>开启</option>';
                                                $html .='<option value="0">关闭</option>';
                                            }else{
                                                $html = '<option value="0" selected>关闭</option>';
                                                $html .='<option value="1">开启</option>';
                                            }
                                            echo $html;
                                        ?>
                                    </select>
                                    是否开启日志
                                </label>
                            </td>
                        </tr>
                        <!-- <tr>
                            <th class="hfcm-th-width">
                                设置所有缓存键的前缀
                            </th>
                            <td>
                                <input type="text" name="cache_key_salt" value="<?php echo esc_attr( get_option('hfcm_redis_cache_key_salt') ); ?>"
                                    class="regular-text"/>
                                <p class="description">Wordpress多站点模式下使用</p>
                            </td>
                        </tr> -->
                    </tbody>
                </table>
                <div class="nnr-mt-20">
                    <div class="nnr-mt-20 nnr-hfcm-codeeditor-box">
                        <div class="wp-core-ui">
                            <input type="submit"
                                name="save_redis_set"
                                value="保存更改"
                                class="button button-primary button-large nnr-btnsave">
                        </div>
                    </div>
                </div>
            </form>
</div>