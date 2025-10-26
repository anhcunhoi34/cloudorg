<?php

require_once __DIR__ . '/../../../../init.php';
use Illuminate\Database\Capsule\Manager as Capsule;

class CrontabController
{

    /**
     * GET TOKEN
    */
    public function getToken() {
        $logFile = __DIR__ . '/cloudnest.log';
        $servers = Capsule::table('tblservers')->where("type", 'cloudnest')->get();
        foreach ($servers as $server) {
            $auth = [
                'serverusername' => $server->username,
                'serverpassword' => decrypt($server->password),
                'serveraccesshash' => $server->accesshash,
            ];
            $getToken = $this->connectCloudnest($auth, 'POST', 'get-token');
            // file_put_contents($logFile, date('H:i:s d-m-Y') . ': ' . 'Get token: ' . PHP_EOL, FILE_APPEND);
            // file_put_contents($logFile, json_encode($getToken) . PHP_EOL, FILE_APPEND);
            if ($getToken->error == 0) {
                $cloudzoneserverauth = Capsule::table('cloudzoneserverauth')->where("serverid", $server->id)->first();
                if ( isset($cloudzoneserverauth) ) {
                    Capsule::table('cloudzoneserverauth')
                        ->where('serverid', $cloudzoneserverauth->id)
                        ->update([ 
                            'auth_token' => $getToken->{'auth-token'},
                            'updated_at' => date('Y-m-d H:i:s')
                        ]);
                }
                else {
                    Capsule::table('cloudzoneserverauth')->insert([
                        'serverid' => $server->id,
                        'auth_token' => $getToken->{'auth-token'},
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);
                }
            }
        }
    }

    function cloudnest_getAuthToken($serverid) {
        return Capsule::table('cloudzoneserverauth')->where("serverid", $serverid)->first();
    }

    /**
     * TẠO VPS ĐANG Ở TRẠNG THÁI CHỜ TẠO
    */
    public function createVpsWaiting() {
        $logFile = __DIR__ . '/cloudnest.log';
        // Kiểm tra xem Capsule đã được thiết lập đúng cách chưa
        if (!Capsule::schema()->hasTable('tblhosting')) {
            return "Bảng 'tblhosting' không tồn tại.";
        }

        $listServiceWaiting = Capsule::table('tblhosting')->where('domainstatus', 'Active')->where("status_vps", 'waiting')->get();

        foreach ($listServiceWaiting as $service) {
            $checkMunite = $this->diffTimeByMinute($service->updated_at);
            if ($checkMunite >= 10) {             
                Capsule::table('tblhosting')
                    ->whereId($service->id)
                    ->update([
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);
                
                $product = Capsule::table('tblproducts')->where("id", $service->packageid)->first();
                if (isset($product)) { 
                    $dataBody = [
                        "product-id" => $product->configoption1,
                        "billing-cycle" => strtolower($service->billingcycle),
                        "quantity" => 1,
                        "addon-cpu" => 0,
                        "addon-ram" => 0,
                        "addon-disk" => 0,
                        "os" => "",
                    ];

                    $serviceConfigoptions = Capsule::table('tblhostingconfigoptions')
                        ->where('relid', $service->id)
                        ->get();
                    if (count($serviceConfigoptions)) {
                        foreach ($serviceConfigoptions as $serviceConfigoption) {
                            $productconfigoption = Capsule::table('tblproductconfigoptions')->whereId($serviceConfigoption->configid)->first();
                            if (isset($productconfigoption)) {
                                if ( strpos(strtolower($productconfigoption->optionname), 'cpu') !== false) {
                                    $dataBody['addon-cpu'] = (int) $serviceConfigoption->qty;
                                }
                                elseif ( strpos(strtolower($productconfigoption->optionname), 'ram') !== false) {
                                    $dataBody['addon-ram'] = (int) $serviceConfigoption->qty;
                                }
                                elseif ( strpos(strtolower($productconfigoption->optionname), 'disk') !== false) {
                                    $dataBody['addon-disk'] = (int) $serviceConfigoption->qty;
                                }
                                elseif ( strpos(strtolower($productconfigoption->optionname), 'hệ điều hành') !== false) {
                                    $productConfigOptionsSubs = Capsule::table('tblproductconfigoptionssub')->where('id', $serviceConfigoption->optionid)->first();
                                    $dataBody['os'] = !empty($productConfigOptionsSubs->optionname) ? $productConfigOptionsSubs->optionname : '';
                                }
                                elseif ( strpos(strtolower($productconfigoption->optionname), 'os') !== false) {
                                    $productConfigOptionsSubs = Capsule::table('tblproductconfigoptionssub')->where('id', $serviceConfigoption->optionid)->first();
                                    $dataBody['os'] = !empty($productConfigOptionsSubs->optionname) ? $productConfigOptionsSubs->optionname : '';
                                }
                            }
                        }
                    }

                    $server = Capsule::table('tblservers')->where("id", $service->server)->first();
                    $cloudzoneserverauth = $this->cloudnest_getAuthToken($server->id);
                    $auth = [
                        'serverusername' => $server->username,
                        'serverpassword' => decrypt($server->password),
                        'serveraccesshash' => $server->accesshash,
                        'servertoken' => $cloudzoneserverauth->auth_token
                    ];
                    file_put_contents($logFile, date('H:i:s d-m-Y') . ': ' . 'Crontab Tạo VPS: ' . PHP_EOL, FILE_APPEND);
                    file_put_contents($logFile, json_encode($dataBody) . PHP_EOL, FILE_APPEND);

                    $response = $this->connectCloudnest($auth, 'POST', 'order/create-order-vps-by-whmcs', $dataBody);
                    file_put_contents($logFile, date('H:i:s d-m-Y') . ': ' . 'Crontab DATA Tạo VPS: ' . PHP_EOL, FILE_APPEND);
                    file_put_contents($logFile, json_encode($response) . PHP_EOL, FILE_APPEND);

                    if (!empty($response)) {
                        if ( !empty($response->error) ) {
                            return $response->message;
                        }
                
                        foreach ($response->data as $key => $dataResponse)
                        {
                            $dataVps = [
                                'vm_id' =>  $dataResponse->{"vps-id"},
                                'nextduedate' =>  $dataResponse->{"next_due_date"},
                                'status_vps' =>  $dataResponse->{"vps-status"},
                                'domain' =>  $dataResponse->{"ip"},
                                'username' =>  $dataResponse->{"username"},
                                'password' =>  $dataResponse->{"password"},
                                'updated_at' => date('Y-m-d H:i:s'),
                                'vps_os' => $dataResponse->{"vps-os"},
                            ];
                
                            Capsule::table('tblhosting')
                                ->whereId($service->id)
                                ->update($dataVps);

                            logModuleCall("cloudnest", 'crontabCreateVpsWaiting', $dataBody, $dataVps, [], []);
                        }
                    }
                }
            }
        }

    }

    /**
     * CẬP NHẬT TRẠNG THÁI VPS ĐANG Ở TRẠNG THÁI CÁC TRẠNG THÁI (ĐANG TẠO, CÀI ĐẶT LẠI,...)
    */
    public function updateStatusVps() {
        $logFile = __DIR__ . '/cloudnest.log';
        // Kiểm tra xem Capsule đã được thiết lập đúng cách chưa
        if (!Capsule::schema()->hasTable('tblhosting')) {
            return "Bảng 'tblhosting' không tồn tại.";
        }

        $listService = Capsule::table('tblhosting')->where('domainstatus', 'Active')->whereIn("status_vps", ['progressing', 'rebuild'])->get();
        file_put_contents($logFile, date('H:i:s d-m-Y') . ': ' . 'Cập nhật VPS: ' . PHP_EOL, FILE_APPEND);
        file_put_contents($logFile, json_encode($listService) . PHP_EOL, FILE_APPEND);

        if (count($listService)) {
            foreach ($listService as $service) {
                $checkMunite = $this->diffTimeByMinute($service->updated_at);
                
                if ($checkMunite >= 2) {             
                    Capsule::table('tblhosting')
                        ->whereId($service->id)
                        ->update([
                            'updated_at' => date('Y-m-d H:i:s')
                        ]);
                    
                    $server = Capsule::table('tblservers')->where("id", $service->server)->first();
                    $cloudzoneserverauth = $this->cloudnest_getAuthToken($server->id);
                    $auth = [
                        'serverusername' => $server->username,
                        'serverpassword' => decrypt($server->password),
                        'serveraccesshash' => $server->accesshash,
                        'servertoken' => $cloudzoneserverauth->auth_token
                    ];
                    $dataBody = [
                        "vps-id" => $service->vm_id,
                    ];

                    $response = $this->connectCloudnest($auth, 'GET', 'vps/get-info-vps', $dataBody);
                    if ( !empty($response) ) {
                        if ( $response->error == 0 ) {
                            $dataResponse = $response->data;
                            file_put_contents($logFile, date('H:i:s d-m-Y') . ': ' . 'Cập nhật VPS: ' . PHP_EOL, FILE_APPEND);
                            file_put_contents($logFile, json_encode($dataResponse) . PHP_EOL, FILE_APPEND);

                            $dataUpdate = [
                                'vm_id' =>  $dataResponse[0]->{"vps-id"},
                                'nextduedate' =>  $dataResponse[0]->{"next_due"},
                                'status_vps' =>  $dataResponse[0]->{"vps-status"},
                                'domain' =>  $dataResponse[0]->{"ip"},
                                'username' =>  $dataResponse[0]->{"username"},
                                'password' =>  $dataResponse[0]->{"password"},
                                'updated_at' => date('Y-m-d H:i:s'),
                                'vps_os' =>  $dataResponse->{"os"},
                            ];
                            logModuleCall("cloudnest", 'upgradeVPS', $dataBody, $dataResponse, "", []);
            
                            $service = Capsule::table('tblhosting')
                                ->where('id', $service->id)
                                ->update( $dataUpdate );
                        }
                        else {
                            file_put_contents($logFile, date('H:i:s d-m-Y') . ': ' . 'Lỗi Cập nhật trạng thái VPS: ' . PHP_EOL, FILE_APPEND);
                            file_put_contents($logFile, $response->message . PHP_EOL, FILE_APPEND);
                        }
                    }
                }

            }
        }
    }

    private function diffTimeByMinute($time) {
        $diff = abs(time() - strtotime($time));

        $years = floor($diff / (365*60*60*24));  


        $months = floor(($diff - $years * 365*60*60*24) 
                                       / (30*60*60*24));  
          
          
        $days = floor(($diff - $years * 365*60*60*24 -  
                     $months*30*60*60*24)/ (60*60*24)); 
          
          
        $hours = floor(($diff - $years * 365*60*60*24  
               - $months*30*60*60*24 - $days*60*60*24) 
                                           / (60*60));  
          
        $minutes = floor(($diff - $years * 365*60*60*24  
                 - $months*30*60*60*24 - $days*60*60*24  
                                  - $hours*60*60)/ 60); 

        return $minutes;
    }

    // CONNECT
    private function connectCloudnest($params, $method, $url, $dataBody = []) {
        $logFile = __DIR__ . '/cloudnest.log';
        $urlConnect = 'https://client.cloudnest.vn/api/agency/';
        try {
            $curl = curl_init();

            if ( $url == 'get-token' ) {     
                $authen = [
                    "api-username" => !empty($params['serverusername']) ? $params['serverusername'] : '',
                    "api-app" => !empty($params['serverpassword']) ? $params['serverpassword'] : '',
                    "api-secret" => !empty($params['serveraccesshash']) ? $params['serveraccesshash'] : '',
                    "auth-token" => !empty($params['servertoken']) ? $params['servertoken'] : '',
                ];
                // file_put_contents($logFile, json_encode($authen) . PHP_EOL, FILE_APPEND);
                $urlcloudnest = $urlConnect . $url; 
                // file_put_contents($logFile, json_encode($urlcloudnest) . PHP_EOL, FILE_APPEND);
       
                curl_setopt_array($curl, array(
                    CURLOPT_URL => $urlcloudnest,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => '',
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 30,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => $method,
                    CURLOPT_POSTFIELDS => json_encode($authen),
                    CURLOPT_HTTPHEADER => array(
                        'Content-Type: application/json'
                    ),
                ));
            }
            else {
                $urlcloudnest = $urlConnect . $url; 

                $contenType = 'Content-Type: application/json';
                $apiName = 'api-username: '; 
                $apiName .= !empty($params['serverusername']) ? $params['serverusername'] : '';
                $apiApp = 'api-app: '; 
                $apiApp .= !empty($params['serverpassword']) ? $params['serverpassword'] : '';
                $apiSecret = 'api-secret: '; 
                $apiSecret .= !empty($params['serveraccesshash']) ? $params['serveraccesshash'] : '';
                $apiToken = 'auth-token: '; 
                $apiToken .= !empty($params['servertoken']) ? $params['servertoken'] : '';
                // file_put_contents($logFile, json_encode([
                //     $contenType, $apiName, $apiApp, $apiSecret, $apiToken 
                // ]) . PHP_EOL, FILE_APPEND);
                // file_put_contents($logFile, $urlcloudnest . PHP_EOL, FILE_APPEND);

                curl_setopt_array($curl, array(
                    CURLOPT_URL => $urlcloudnest,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => '',
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 300,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => $method,
                    CURLOPT_POSTFIELDS => json_encode($dataBody),
                    CURLOPT_HTTPHEADER => [
                        $contenType, $apiName, $apiApp, $apiSecret, $apiToken 
                    ],
                ));
            }

    
            $response = curl_exec($curl);
            curl_close($curl);
            
            // file_put_contents($logFile, json_encode($response) . PHP_EOL, FILE_APPEND);
            return json_decode($response);

        } 
        catch (\Throwable $th) {
            file_put_contents($logFile, $th . PHP_EOL, FILE_APPEND);

            return array("error" => "1", "details" => "Lỗi kết nối");
        }
    }


    /**
     * GIA HẠN VPS
    */
    public function renewVps($serviceId, $billingCycle)
    {
        $logFile = __DIR__ . '/cloudnest.log';
        $service = Capsule::table('tblhosting')
            ->where('id', $serviceId)
            ->first();

        if (isset($service)) {
            $server = Capsule::table('tblservers')->where("id", $service->server)->first();
            $cloudzoneserverauth = $this->cloudnest_getAuthToken($server->id);
            $auth = [
                'serverusername' => $server->username,
                'serverpassword' => decrypt($server->password),
                'serveraccesshash' => $server->accesshash,
                'servertoken' => $cloudzoneserverauth->auth_token
            ];
            $dataBody = [
                "action" => 'renew-vps',
                "vps-id" => $service->vm_id,
                "billing-cycle" => strtolower($billingCycle),
            ];

            
            $response = $this->connectCloudnest($auth, 'POST', 'vps/action-vps', $dataBody);
            
            if ( !empty($response) ) {
                file_put_contents($logFile, date('H:i:s d-m-Y') . ': ' . 'Gia hạn VPS: ' . PHP_EOL, FILE_APPEND);
                file_put_contents($logFile, json_encode($response) . PHP_EOL, FILE_APPEND);

                if ( $response->error == 0 ) {
                    $dataUpdate = [
                        'nextduedate' =>  $response->{"next_due_date"},
                        'updated_at' => date('Y-m-d H:i:s'),
                        'billingcycle' => $billingCycle
                    ];
                    if ( $server->status_vps == 'expire' ) {
                        $dataUpdate['status_vps'] = 'on';
                    }
                    logModuleCall("cloudnest", 'autoRenewVPS', $dataBody, $response, "", []);
    
                    $service = Capsule::table('tblhosting')
                        ->where('id', $service->id)
                        ->update( $dataUpdate );
                    
                    return [ 'error' => 0, 'message' => "Gia hạ thành công" ];
                }
                else {
                    return [
                        'error' => 1,
                        'message' => $response->message
                    ];
                }
            }
        }
        else {
            return [
                'error' => 1,
                'message' => 'Dịch vụ VPS không tồn tại'
            ];
        }
    }

    /**
     * ADDON VPS
    */
    public function addonVps($serviceId, $addonCpu, $addonRam, $addonDisk)
    {
        $logFile = __DIR__ . '/cloudnest.log';
        
        $service = Capsule::table('tblhosting')
            ->where('id', $serviceId)
            ->first();

        if (isset($service)) {
            $server = Capsule::table('tblservers')->where("id", $service->server)->first();
            $cloudzoneserverauth = $this->cloudnest_getAuthToken($server->id);
            $auth = [
                'serverusername' => $server->username,
                'serverpassword' => decrypt($server->password),
                'serveraccesshash' => $server->accesshash,
                'servertoken' => $cloudzoneserverauth->auth_token
            ];
            $dataBody = [
                "action" => 'addon-vps',
                "vps-id" => $service->vm_id,
                "addon-cpu" => $addonCpu,
                "addon-ram" => $addonRam,
                "addon-disk" => $addonDisk,
            ];

            
            $response = $this->connectCloudnest($auth, 'POST', 'vps/action-vps', $dataBody);
            
            if ( !empty($response) ) {
                file_put_contents($logFile, date('H:i:s d-m-Y') . ': ' . 'Nâng cấp VPS: ' . PHP_EOL, FILE_APPEND);
                file_put_contents($logFile, json_encode($response) . PHP_EOL, FILE_APPEND);

                if ( $response->error == 0 ) {
                    logModuleCall("cloudnest", 'addonVps', $dataBody, $response, "", []);                    
                    return [ 'error' => 0, 'message' => "Nâng cấp thành công" ];
                }
                else {
                    return [
                        'error' => 1,
                        'message' => $response->message
                    ];
                }
            }
        }
        else {
            return [
                'error' => 1,
                'message' => 'Dịch vụ VPS không tồn tại'
            ];
        }
    }


}