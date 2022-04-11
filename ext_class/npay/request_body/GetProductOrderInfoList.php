<?
$request_body="<soapenv:Envelope xmlns:soapenv=\"http://schemas.xmlsoap.org/soap/envelope/\" xmlns:mall=\"http://mall.checkout.platform.nhncorp.com/\" xmlns:base=\"http://base.checkout.platform.nhncorp.com/\">
   <soapenv:Header/>
   <soapenv:Body>
      <mall:GetProductOrderInfoListRequest>
         <base:AccessCredentials>
            <base:AccessLicense>".$accessLicense."</base:AccessLicense>
            <base:Timestamp>".$timestamp."</base:Timestamp>
            <base:Signature>".$signature."</base:Signature>
         </base:AccessCredentials>
         <base:RequestID></base:RequestID>
         <base:DetailLevel>".$detailLevel."</base:DetailLevel>
         <base:Version>".$version."</base:Version>
         <mall:ProductOrderIDList>".$sProductOrderID."</mall:ProductOrderIDList>
      </mall:GetProductOrderInfoListRequest>
   </soapenv:Body>
</soapenv:Envelope>";
?>