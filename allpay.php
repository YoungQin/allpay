<?php 
    
    /**
     *  Created by PhpStorm.
     * User: Mr.gong
     * Date: 2016/9/13 
     * Time: 14:22 
     * 微信 支付宝app支付  安卓ios通吃  v2.0 
     * 获取支付url
     * type alipay  wxpay  
     * out_trade_no 订单号
     * subject 自定义
     * body 自定义
     * total_fee 金额
     *
     */
    private function getpayurl($type,$out_trade_no,$subject,$body,$total_fee){


    if($type == 'alipay'){
        //支付宝签名

        $notify_url = 'http://www.alipay.cn/server/notify_url.php'; //回调地址
        $path = BASE_ROOT_PATH.DS.DIR_SHOP.DS.'control/res_pay.txt'; // 私钥地址  根据当前框架的路径变量填写的绝对路径
         // $path = BASE_ROOT_PATH.DS.DIR_SHOP.DS.'control/key/rsa_private_key.pem';
        $privateKey = file_get_contents($path);
        $partner = "208812146xxxxxxx"; // 商家id 2088开头 16位 
        $seller = "www@baidu.com"; //商家名称
        $dataString = array(
            "app_id"        => "2016090801xxxxxx",//appid
            "method"        => "alipay.trade.app.pay",//无需修改
            "notify_url"    =>  $notify_url, //此参数可选  开发平台填写了授权回调地址的 话这里无需填写
            "sign_type"     => "RSA", //无需修改
            "version"       => "1.0", //当前app支付版本 无需修改
            "timestamp"     => date('Y-m-d H:i:s',time()),//yyyy-MM-dd HH:mm:ss
            "biz_content"   => '{"timeout_express":"60m","seller_id":"","product_code":"QUICK_MSECURITY_PAY","total_amount":"'.$total_fee.'","subject":"'.$subject.'","body":"'.$body.'","out_trade_no":"'.$out_trade_no.'"}',
            "charset"       => "utf-8",
            "format"        => "json"
        );

        ksort( $dataString );

        //重新组装参数
        $params = array();
        foreach($dataString as $key => $value){
            //生成加密的签名参数
            $params[] = $key .'='. rawurlencode($value);
            // 生成未加密的签名参数  用此参数去签名
            $signparams[] = $key .'='. $value;
        }

        //2种参数 都用&符合拼接
        $dataString = implode('&', $params);

        $signString = implode('&', $signparams);
        

        $res = openssl_get_privatekey($privateKey);
  
        openssl_sign($signString, $sign, $res,OPENSSL_ALGO_SHA1);
     
        openssl_free_key($res);
        
        $sign = urlencode(base64_encode($sign));


        $dataString.='&sign='.$sign;
        
        
        $return_data['ios'] = $dataString;
        $return_data['android'] =$dataString;
        return $return_data;
        }else if($type == 'wxpay'){
            //微信签名

            // STEP 0. 账号帐户资料
            //更改商户把相关参数后可测试
            $APP_ID="wx51800ace30xxxxxx";          //APPID
            $APP_SECRET="xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx";//appsecret
    
            //商户号，填写商户对应参数
            $MCH_ID="1379xxxxxx";
            //商户API密钥，填写相应参数
            $PARTNER_ID="xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx";
            //支付结果回调页面
            $NOTIFY_URL= 'https://weixin.qq.com/notify_url.php';

            //STEP 1. 构造一个订单。
            $order=array(
                "body" => $out_trade_no,
                "appid" => $APP_ID,
                "device_info" => "APP-001",
                "mch_id" => $MCH_ID,
                "nonce_str" => mt_rand(),
                "notify_url" => $NOTIFY_URL,
                "out_trade_no" =>$out_trade_no,
                "spbill_create_ip" => "196.168.1.1",
                "total_fee" => $total_fee *100,//坑！！！这里的最小单位时分，跟支付宝不一样。1就是1分钱。只能是整形。
                "trade_type" => "APP"
                );
            ksort($order);

            //STEP 2. 签名
            $sign="";
            foreach ($order as $key => $value) {
                if($value&&$key!="sign"&&$key!="key"){
                    $sign.=$key."=".$value."&";
                }
            }
            $sign.="key=".$PARTNER_ID;
            $sign=strtoupper(md5($sign));

            //STEP 3. 请求服务器
            $xml="<xml>\n";
            foreach ($order as $key => $value) {
                $xml.="<".$key.">".$value."</".$key.">\n";
            }
            $xml.="<sign>".$sign."</sign>\n";
            $xml.="</xml>";
            $opts = array(
                'http' =>
                array(
                    'method'  => 'POST',
                    'header'  => 'Content-type: text/xml',
                    'content' => $xml
                    ),
                "ssl"=>array(
                    "verify_peer"=>false,
                    "verify_peer_name"=>false,
                    )
                );
            $context  = stream_context_create($opts);
            $result = file_get_contents('https://api.mch.weixin.qq.com/pay/unifiedorder', false, $context);
            $result = simplexml_load_string($result,null, LIBXML_NOCDATA);
            //在此打印出 result 可以看出各项参数是否正确

            //使用$result->nonce_str和$result->prepay_id。再次签名返回app可以直接打开的链接。
            $input=array(
                "noncestr"=>"".$result->nonce_str,
                "prepayid"=>"".$result->prepay_id,//上一步请求微信服务器得到nonce_str和prepay_id参数。
                "appid"=>$APP_ID,
                "package"=>"Sign=WXPay",
                "partnerid"=>$MCH_ID,
                "timestamp"=>time(),
                );
            ksort($input);
            $sign="";
            foreach ($input as $key => $value) {
                if($value&&$key!="sign"&&$key!="key"){
                    $sign.=$key."=".$value."&";
                }
            }
            $sign.="key=".$PARTNER_ID;
            $sign=strtoupper(md5($sign));
            $iOSLink=sprintf("weixin://app/%s/pay/?nonceStr=%s&package=Sign%%3DWXPay&partnerId=%s&prepayId=%s&timeStamp=%s&sign=%s&signType=SHA1",$APP_ID,$input["noncestr"],$MCH_ID,$input["prepayid"],$input["timestamp"],$sign);
            $androidLink = sprintf("{nonceStr=%s,package='Sign=WXPay',partnerId=%s,prepayId=%s,timeStamp=%s,sign=%s,signType=SHA1}",$input["noncestr"],$MCH_ID,$input["prepayid"],$input["timestamp"],$sign);
            
            $return_data['ios'] = $iOSLink;
            $return_data['android'] = $androidLink;
            return $return_data;
        }
    }
     ?>