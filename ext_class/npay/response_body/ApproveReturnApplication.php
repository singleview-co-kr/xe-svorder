<?
/*
// 반품 수거완료 실패
$sResponse = "";
*/

/*
// 반품 수거완료 성공
$sResponse = "<?xml version='1.0' encoding='utf-8'><soapenv:Envelope xmlns:soapenv=\"http://schemas.xmlsoap.org/soap/envelope/\" xmlns:n1=\"http://base.checkout.platform.nhncorp.com/\" xmlns:n=\"http://mall.checkout.platform.nhncorp.com/\"&gt;<soapenv:Body&gt;<n:ApproveReturnApplicationResponse&gt;<n1:RequestID/&gt;<n1:ResponseType&gt;SUCCESS</n1:ResponseType&gt;<n1:ResponseTime&gt;954</n1:ResponseTime&gt;<n1:DetailLevel&gt;Full</n1:DetailLevel&gt;<n1:Version&gt;4.0</n1:Version&gt;<n1:Release&gt;UNKNOWN</n1:Release&gt;<n1:Timestamp&gt;2019-12-07T23:58:57.46Z</n1:Timestamp&gt;<n1:MessageID&gt;0eylFgAgSriF41WNXFFDZQ</n1:MessageID&gt;</n:ApproveReturnApplicationResponse&gt;</soapenv:Body&gt;</soapenv:Envelope&gt;";
*/

$xmlResponseList = $this->_g_xmlRespBody->getElementsByTagName('ApproveReturnApplicationResponse')->item(0);

if(!$xmlResponseList && count($xmlResponseList)==0)
	$oRst = new BaseObject(-1, 'invalid ApproveReturnApplicationResponse counts');
else
	$oRst = new BaseObject();
?>