<?
$request_body="<soapenv:Envelope xmlns:soapenv=\"http://schemas.xmlsoap.org/soap/envelope/\" xmlns:mall=\"http://mall.checkout.platform.nhncorp.com/\" xmlns:base=\"http://base.checkout.platform.nhncorp.com/\">
   <soapenv:Header/>
   <soapenv:Body>
      <mall:ShipProductOrderRequest>
         <base:AccessCredentials>
            <base:AccessLicense>".$accessLicense."</base:AccessLicense>
            <base:Timestamp>".$timestamp."</base:Timestamp>
            <base:Signature>".$signature."</base:Signature>
         </base:AccessCredentials>
         <base:RequestID></base:RequestID>
         <base:DetailLevel>".$detailLevel."</base:DetailLevel>
         <base:Version>".$version."</base:Version>
         <mall:ProductOrderID>".$sProductOrderID."</mall:ProductOrderID>
         <mall:DeliveryMethodCode>".$sDeliveryMethodCode."</mall:DeliveryMethodCode>
         <mall:DeliveryCompanyCode>".$sDeliveryCompanyCode."</mall:DeliveryCompanyCode>
         <mall:TrackingNumber>".$sTrackingNumber."</mall:TrackingNumber>
         <mall:DispatchDate>".$sDispatchDate."</mall:DispatchDate>
      </mall:ShipProductOrderRequest>
   </soapenv:Body>
</soapenv:Envelope>";

/*$sResponse = "<?xml version='1.0' encoding='utf-8'?><soapenv:Envelope xmlns:soapenv=\"http://schemas.xmlsoap.org/soap/envelope/\" xmlns:n1=\"http://base.checkout.platform.nhncorp.com/\" xmlns:n=\"http://mall.checkout.platform.nhncorp.com/\"><soapenv:Body><n:ShipProductOrderResponse><n1:RequestID/><n1:ResponseType>ERROR</n1:ResponseType><n1:ResponseTime>453</n1:ResponseTime><n1:Error><n1:Code>ERR-NC-104123</n1:Code><n1:Message>惯价 贸府 角菩(老矫利牢 厘局)</n1:Message><n1:Detail>Transaction ID: 1FB560EA931D844A628376460A62F0F9</n1:Detail></n1:Error><n1:DetailLevel>Full</n1:DetailLevel><n1:Version>4.0</n1:Version><n1:Release>UNKNOWN</n1:Release><n1:Timestamp>2019-11-06T01:57:52.68Z</n1:Timestamp><n1:MessageID>JV3MQ2BQUD5EP7JECJGFNF80S400000G</n1:MessageID></n:ShipProductOrderResponse></soapenv:Body></soapenv:Envelope>";*/
?>