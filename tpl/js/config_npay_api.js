jQuery(document).ready(function() {
	jQuery( "#btnCollectOrderForm" ).click(function() {
		var sStartYmd = jQuery( "#npay_api_order_start_ymd" ).val();
		jQuery( "#start_ymd_order" ).val(sStartYmd);
		jQuery( "#collect_order_form" ).submit();
	});
	jQuery( "#btnResetOrderInfoForm" ).click(function() {
		if(confirm("������ npay �ֹ� ������ �ʱ�ȭ�Ͻ÷��� ���� ��������"))
			jQuery( "#reset_order_info_form" ).submit();
	});
	jQuery( "#btnCollectReviewForm" ).click(function() {
		var sStartYmd = jQuery( "#npay_api_review_start_ymd" ).val();
		jQuery( "#start_ymd_review" ).val(sStartYmd);
		jQuery( "#collect_review_form" ).submit();
	});
	jQuery( "#btnCollectInquiryForm" ).click(function() {
		var sStartYmd = jQuery( "#npay_api_inquiry_start_ymd" ).val();
		jQuery( "#start_ymd_inquiry" ).val(sStartYmd);
		jQuery( "#collect_inquiry_form" ).submit();
	});
});