<?
$request_body="<soapenv:Envelope xmlns:soapenv=\"http://schemas.xmlsoap.org/soap/envelope/\" xmlns:mall=\"http://mall.checkout.platform.nhncorp.com/\" xmlns:base=\"http://base.checkout.platform.nhncorp.com/\">
   <soapenv:Header/>
   <soapenv:Body>
      <mall:GetChangedProductOrderListRequest>
         <base:AccessCredentials>
            <base:AccessLicense>".$accessLicense."</base:AccessLicense>
            <base:Timestamp>".$timestamp."</base:Timestamp>
            <base:Signature>".$signature."</base:Signature>
         </base:AccessCredentials>
         <base:RequestID></base:RequestID>
         <base:DetailLevel>".$detailLevel."</base:DetailLevel>
         <base:Version>".$version."</base:Version>
         <base:InquiryTimeFrom>".$sInquiryTimeFrom."</base:InquiryTimeFrom>
         <base:InquiryTimeTo>".$sInquiryTimeTo."</base:InquiryTimeTo>
         <base:InquiryExtraData></base:InquiryExtraData>
         <mall:LastChangedStatusCode></mall:LastChangedStatusCode>
         <mall:MallID></mall:MallID>
      </mall:GetChangedProductOrderListRequest>
   </soapenv:Body>
</soapenv:Envelope>";
?>