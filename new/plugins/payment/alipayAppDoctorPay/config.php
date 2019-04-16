<?php
//$config = array (
//		//应用ID,您的APPID。
//		/*'app_id' => "2016080500173270",*/
//		'app_id' => "2017061407489692",
//
//		//商户私钥，您的原始格式RSA私钥
//		/*'merchant_private_key' => "MIIEvwIBADANBgkqhkiG9w0BAQEFAASCBKkwggSlAgEAAoIBAQDE3p+9VB0d3whc6BCBuduDDalJPNTlNRwRAh8FQq6Ij76bGEKqFqMVoPCsqNAFIa3oQljNEr2QGw5OAfgVs7WqD5DdYyWY+u04dup+AgM7hF0BSLx+O/SJ2Txs8Z4U0dtinnG5PPHgfdY1EA1YiRZ/a5XlAOEthexIyNzuTeTsf6jIKJCYe/4JWtlqs2eCimVbaZvJAIT4kKqRwq63piHX0JdL5PEVWZDNJaBViGdbgDBQ8tUxGdUeZ06vcI7BHY1d6+4dI3hI8K9W6R6KGaC+NDWJRfLX/PlTUw0plAsBxKhyqACUMlXcCk+IsYQaP2v6dpXMPqp7ToLGDRo7yq2jAgMBAAECggEBALaVNhcUZLCoggwQWgbGRZSE3gUDevtcxzvq+LQbRN14WzFiTamLtxK/IZcwNUUdGDn5FnyTLeXxgdHNN7WA5zHin9sDMgJwbgPZHd9hpHfVaaBgQhjdKA2UwNrVN2RdwRb0lcpTgIxQiJLL4WTEN25TbEBoEW7z7tEOIQCOk4rVtXUKYNImXsJiC3SuIsvpoalDXOxuI3kOV2MzxCSNXFtDKXH3Mz2y2OSfAQnR1VS/82WITEBFgdmT8EAA/zpX7pfQ17MklG+oeE0xG+tGFJ0HXmeFaTDG6rp9iaTgBn75Bx3mcTf6ek53LQL3kGZPOy/sGkBZB1rQPE3GCin91LECgYEA78TCvpQk7AQPed4BaYj7r1Mj1D1klU15rmq/r61r8v9nP1n98RBzxphbu/scZlojHWDrrIWMvhzpXlDwkE7TSLWqDYa8q67EViAkDkjs5wPvh5xcpzZAlAwmPO79hpLd5JWnFqwbhcr3x3ld5QzHZDClVjdgGRdkr78N76/4Q5kCgYEA0jJqTFOea10C1mrYryzeWJ5JIZ3REJZWvN3+myaKl3cDwU/jC1Y3HSTSbTfAI5lUco+t3DxEUBwQuxXaYzR1p5cdATECOfYzz+JP60b3S2bzHpR+LUa3sW0qLRxi39Wb9Us1UjcfFFQI0SSmFm8BCiEmEeb2FGZ66L9te6hfwJsCgYAzK/gwOmfi4z72UHZWssCce48DBhNIdh+JJQlCEi4ez3mWoLs8zrkW4n95kC9QfN5Pr9a8nEgcbwkzcuzUr64SL4tala4Aqi0HAJcRYWkGKOXfOHThzwdbUXh7urnrFb87wODHSvYYyOe27+UlNGP9sxAmZW0iDccUZx8vT4wZyQKBgQCmuNJThCetpPKxO8Ju/7nEtJfxEApZi8V0vOhMC7/177KMzF6cCWBhv2kgtA20rtOqoogWnb7Zg8lJe0XjViLUMSoSXdsUOlJSW4Fkbr+LkTbZVJLYOrVNB/diHJfYTsgLQgIjGhGOwDDWIqmSNa2vaSeXH4ikGbz3i469fImmbQKBgQDH7RYkSAQFbac05/8ksAc5N/ZtwBSTk+APTe1mhSvX5b7SRqTSv3WsximRwJxF+Rj4G8wDfj2Jt9v+m+Cs8CNR2nay3kDPwSBxMcqH1909iC3N8VDeL05FIumBC5YMR+vxD4547zWXubw17Dlqc714Msg+PWOLTgI0WZZ2JQaNbw==",
//		*/
//		'merchant_private_key' =>"MIIEpQIBAAKCAQEAwh1uQD2IMFWhW4pypLPWdB1xK5a8cD8KziXCEtTLw53Isd+GTZlS0NMdojCOdY6qCkeBtpw1jbAg0E9L6xNFf6Gh/zC3iMqqKJQbO7V98ZYvay2v547RM5vjcCKzQK9e6v8ZqnEA7l4atPhFa3FlYyN5y8PC5o86XZyse/GP+Bm99x76XWAfWIfwKk3UooduDG7G/Pz+Bty1aurs+6mrkeLs/Q48CcTTRur4U44i1Mq3bhC16ld1ZdXUadH8KerD4EZK5DkWZNQLrFwqVHamP6cVqcdldCJSBnLBY1xpp+btvAJcpjZ6iz0pW6OUxewW2kS4HZkrZ5gmClQ8rGsgRQIDAQABAoIBAQC03cdEhBDJOIBIUw/O9uHy/xvbiW+7BoKC4huagDv74KCHW8Y/t4S6nnsWDDgwwOUe/tGV3KbphhqM/dW3CxXOfkP7CuF3y54QgS9+yvS2jUFRlBg6oY+7jpy0dyCePMksHNVFQlWjxHsN8+Z63gWiAgQSIC0RtpKjdsAgHEwQ1gtcsCeAS7MLITtfpuK6a7/ajONutkn47DjkYWtz7gplFkgmsJaN864JvGMxnsujgDticLn5uKFDnqAiKQ2q6kXRBMXA6/5DVZmSDuYHsKRsZPOD6nyC0DMYajxeG9bccywQRmsdGMv0H0EOCtMdJGPQInOsqtvsC3Wu66phOhkBAoGBAOvtWz64c/gpwsaREYC0MchKDwOrOsd3hRZh4L84UUZ7/3OmcAf5QpZH/TBjJhqRLP8kpfSeSbyYHdjOBjMeR8FypveqMBzlDr7scWVAvCHiSgmloqXtonJAP3A4/lAVkFJOGh/1fXnktLzegl8uFmHyyE/b2VMueYZm4wPevVwZAoGBANKhYLWjkUh1N4B1tXWQkSs//V6FVXHTbuOvruvFLwPpPvH9RKIk7KOzqHg56iPSvhbauPLSxGTfSxnS0++ZIuOXqzyH3uuvHGmYI2qHYAvW5spPesXXiuIvuVnIkXXNMKOqVVLpYvAplpbCPInXl/3df62Vs95uru7QJL+bp2sNAoGAFUhcKtRI4eIih/ceNRYMR50mrZYMv2Gwx8wckiNqcYlOCgjBonaB4zyrQmovTcY64OlVbzO4QyMVzjEHriTVJEZLeZwIqxKeuepqcE/eqM/ZDfW7Lmy5csUI8/6wMlk/o60X/joPD6fqBf+skxl9O2jDWTDj8fUHUXCGmhrFykECgYEAmZfQW+Pw13OSi3xKXHaVRYKeEkUMb7qMjD/aQFdD14hIvFHBsLNYVG94FGO3F3Rf5W6Nm5SSXjRuIWCZ54g15tz8o2E474h8IYwtl0ssgLWvCiw3DPoGbrX6ZTxaxhpgs5hMK+/Ak/zfsQPm/WVXkmno5v3ZcgQoUEE76vVoeikCgYEAq5hAxgO+JU2NDzX8PV57lrRfb7U06sA+sLyilKpRAx8L2QLli1fZGqyGrcX1mQpVzVnYMvHbWrySJ9su3uk9bBPz/xLYK1TN5CRKBF2su04W4cH01C91KPS3Fdat79vQmPnN2vFyHOjG+5nt+Wk+h3rA10jQCvg3oppeHLTQ8Rk=",
//		//异步通知地址
//		'notify_url' => "http://www.dev.yzjapp.com/notify_url.php",
//
//		//同步跳转
//		'return_url' => "http://www.m.yzjapp.com/return_url.php",
//
//		//编码格式
//		'charset' => "UTF-8",
//
//		//签名方式
//		'sign_type'=>"RSA2",
//
//		//支付宝网关
//		'gatewayUrl' => "https://openapi.alipay.com/gateway.do", //https://openapi.alipaydev.com/gateway.do
//
//		//支付宝公钥,查看地址：https://openhome.alipay.com/platform/keyManage.htm 对应APPID下的支付宝公钥。
//		/*'alipay_public_key' => "MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAxN6fvVQdHd8IXOgQgbnbgw2pSTzU5TUcEQIfBUKuiI++mxhCqhajFaDwrKjQBSGt6EJYzRK9kBsOTgH4FbO1qg+Q3WMlmPrtOHbqfgIDO4RdAUi8fjv0idk8bPGeFNHbYp5xuTzx4H3WNRANWIkWf2uV5QDhLYXsSMjc7k3k7H+oyCiQmHv+CVrZarNngoplW2mbyQCE+JCqkcKut6Yh19CXS+TxFVmQzSWgVYhnW4AwUPLVMRnVHmdOr3COwR2NXevuHSN4SPCvVukeihmgvjQ1iUXy1/z5U1MNKZQLAcSocqgAlDJV3ApPiLGEGj9r+naVzD6qe06Cxg0aO8qtowIDAQAB",*/
//		'alipay_public_key' => "MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEApfAvr+2eVDIfGmjzVnCmMlIE4ick4rLJ47DM1w00QlTbXqm3D/lFxErHUoHuu96IQ+Bp5JW+442+zYpFHQJ+ZfUgLh9U7NFzUGgvUYAqXJ9cW4vbrMvwxP6+iJVI7qHLCxy79Dl9yFM94pVqITzMTW+6jFaeuC2tjz1xIGBviMWhRcOgQjb2Ub55QR0vfqeGk/gJxjNaeL0ncEF4m0rgqsT2wPEN9UtflUym1M8m3SDARJ7kGH8BEXxZQAjc8o2KlsCeMhdboZfkWc/4CfYBehT9nQECznjoS3RnIjXThsqa4bo7AKOUSfe7Et2LzRoCrzh2Mz2oQxA5oz81GTdblQIDAQAB",
//);
return array(
    'code'=> 'alipayAppDoctorPay',
    'name' => '医生端支付宝',
    'version' => '2.0',
    'author' => 'jeep',
    'desc' => '手机医生端端支付宝',
    'icon' => 'logo.jpg',
    'scene' =>1,  // 使用场景 0 PC+手机 1 手机 2 PC 3 appwebview
    'config' => array(
        array('name' => 'app_id','label'=>'应用ID','type' => 'text','value' => ''),
        array('name' => 'merchant_private_key','label'=>'商户私钥','type' => 'textarea','value' => ''),
        array('name' => 'notify_url','label'=>'异步通知地址','type' => 'text','value' => ''),
        array('name' => 'return_url','label'=>'同步跳转','type' => 'text','value' => ''),
        array('name' => 'charset','label'=>' 编码格式','type' => 'text', 'value' => ''),
        array('name' => 'sign_type','label'=>' 签名方式','type' => 'text', 'value' => ''),
        array('name' => 'gatewayUrl','label'=>' 支付宝网关','type' => 'text', 'value' => ''),
        array('name' => 'alipay_public_key','label'=>' 支付宝公钥','type' => 'textarea', 'value' => ''),
    ),
);
