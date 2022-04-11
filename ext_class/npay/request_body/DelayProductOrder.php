<?
// CANCEL 취소
// RETURN 교환
// EXCHANGE 반품
// PURCHASE_DECISION_HOLDBACK 구매 확정 보류
// ADMIN_CANCEL 직권 취소
$request_body="<soapenv:Envelope xmlns:soapenv=\"http://schemas.xmlsoap.org/soap/envelope/\" xmlns:mall=\"http://mall.checkout.platform.nhncorp.com/\" xmlns:base=\"http://base.checkout.platform.nhncorp.com/\">
   <soapenv:Header/>
   <soapenv:Body>
      <mall:DelayProductOrderRequest>
         <base:AccessCredentials>
            <base:AccessLicense>".$accessLicense."</base:AccessLicense>
            <base:Timestamp>".$timestamp."</base:Timestamp>
            <base:Signature>".$signature."</base:Signature>
         </base:AccessCredentials>
         <base:RequestID></base:RequestID>
         <base:DetailLevel>".$detailLevel."</base:DetailLevel>
         <base:Version>".$version."</base:Version>
         <mall:ProductOrderID>".$sProductOrderID."</mall:ProductOrderID>
         <mall:DispatchDueDate>".$sDispatchDueDate."</mall:DispatchDueDate>
         <mall:DispatchDelayReasonCode>".$sDispatchDelayReasonCode."</mall:DispatchDelayReasonCode>
         <mall:DispatchDelayDetailReason>".$sDispatchDelayDetailReason."</mall:DispatchDelayDetailReason>
      </mall:DelayProductOrderRequest>
   </soapenv:Body>
</soapenv:Envelope>";
?>