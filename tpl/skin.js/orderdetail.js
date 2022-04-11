jQuery(function($){
	jQuery('.receipt').click(function() {
		var order_srl = jQuery(this).attr('data-order-srl');
		var $_parent = jQuery(this).parent();
		exec_xml(
			'svpg',
			'getSvpgReceipt',
			{order_srl:order_srl},
			function(ret){
				var tpl = ret.tpl.replace(/<enter>/g, '\n');
				$_parent.html(tpl);
			},
			['error','message','tpl']
		);
	});

	jQuery('#btn_show_addr').click(function () {
		if(jQuery('#tbl_postcodify_addr').css('display') == 'none')
		{   
			var nOrderSrl =jQuery(this).attr('data-order-srl');
			_checkAddrChangeable( nOrderSrl );
		}
		else
		{
			jQuery('#btn_show_addr').html('변경');
			jQuery('#tbl_postcodify_addr').hide();  
		}
	});

	jQuery('#btn_update_req_addr').click(function () {
		if(jQuery('#tbl_postcodify_addr').css('display') == 'table')
		{   
			var nOrderSrl =jQuery(this).attr('data-order-srl');
			_requestUpdateAddr( nOrderSrl );
		}
		else
			console.log('invalid approach');
	});

	jQuery('.btn_toggle_delivery_memo').click(function () {
		var nOrderSrl = jQuery(this).attr('data-order-srl');
		var nCartSrl = jQuery(this).attr('data-cart-srl');
		var $oTdDelivMemo = jQuery('[data-cart-srl='+nCartSrl+']').parent();
		if($oTdDelivMemo.children('#txt_update_deliv_memo').css('display') == 'none')
			_checkDelivMemoChangeable( nOrderSrl, nCartSrl );
		else
		{
			$oTdDelivMemo.children('#txt_update_deliv_memo').hide();
			$oTdDelivMemo.children('.btn_update_delivery_memo').hide();
		}
	});

	jQuery('.btn_update_delivery_memo').click(function () {
		var nCartSrl = jQuery(this).attr('data-cart-srl');
		var nOrderSrl = jQuery(this).attr('data-order-srl');
		var $oTdDelivMemo = jQuery(this).parent();
		var sDelivMemo = $oTdDelivMemo.children('#txt_update_deliv_memo').val();
		if($oTdDelivMemo.children('#txt_update_deliv_memo').css('display') != 'none' )
			_requestUpdateDelivMemo( nOrderSrl, nCartSrl, sDelivMemo );
	});
});

jQuery(function() {
	jQuery('#target_order_status').change(function(){
		if( jQuery( this ).val() == '')
			jQuery('#OrderStatusUpdateForm').html('');
		else
		{
			var params = new Array();
			params['svorder_mid'] = _g_sSvorderMid;
			params['tgt_status'] = jQuery( this ).val();
			params['order_srl'] = jQuery( this ).attr('data-order-srl'); // for cancel_request and cancelled only
			exec_xml('svorder', 'getSvorderOrderStatusUpdateForm', params, function(ret_obj){
				var sTpl = ret_obj.tpl.replace(/<enter>/g, '\n');
				jQuery('#OrderStatusUpdateForm').html(sTpl);
			},['error','message','tpl']);
		}
	});
});

function _requestUpdateDelivMemo( nOrderSrl, nCartSrl, sDelivMemo )
{
	if( typeof nOrderSrl == 'undefined' )
	{
		alert( '잘못된 주문번호입니다.' );
		return;
	}

	if( nOrderSrl.length == 0 )
	{
		alert( '잘못된 주문번호입니다.' );
		return;
	}

	if( typeof nCartSrl == 'undefined' )
	{
		alert( '잘못된 장바구니 번호입니다.' );
		return;
	}

	if( nCartSrl.length == 0 )
	{
		alert( '잘못된 장바구니 번호입니다.' );
		return;
	}

	if( typeof sDelivMemo != 'string' )
	{
		alert( '잘못된 배송메모입니다.' );
		return;
	}

	var params = new Array();
	params['order_srl'] = nOrderSrl;
	params['cart_srl'] = nCartSrl;
	params['deliv_memo'] = sDelivMemo;
	var response = ['is_changed'];
	exec_xml('svorder', 'procSvorderUpdateDelivMemo', params, function(ret_obj){
		if( ret_obj['is_changed'] == '1' )
			location.reload();
		else
			alert( ret_obj['message'] );
	}, response);
}

function _requestUpdateAddr( nOrderSrl )
{
	if( typeof nOrderSrl == 'undefined' )
	{
		alert( '잘못된 주문번호입니다.' );
		return;
	}

	if( nOrderSrl.length == 0 )
	{
		alert( '잘못된 주문번호입니다.' );
		return;
	}
	var sPostcode = jQuery('#postcodify_1_postcode').val();
	var sAddress = jQuery('#postcodify_1_address').val();
	var sJibeonAddress = jQuery('#postcodify_1_jibeon_address').val();
	var sDetails = jQuery('#postcodify_1_details').val();
	var sExtraInfo = jQuery('#postcodify_1_extra_info').val();
	var params = new Array();
	params['order_srl'] = nOrderSrl;
	params['postcode'] = sPostcode;
	params['address'] = sAddress;
	params['jibeon_address'] = sJibeonAddress;
	params['details'] = sDetails;
	params['extra_info'] = sExtraInfo;
	var response = ['is_changed'];
	exec_xml('svorder', 'procSvorderUpdateAddress', params, function(ret_obj){
		if( ret_obj['is_changed'] == '1' )
			location.reload();
		else
			alert( ret_obj['message'] );
	}, response);
}

function _checkDelivMemoChangeable( nOrderSrl, nCartSrl )
{
	if( typeof nOrderSrl == 'undefined' )
	{
		alert( '잘못된 주문번호입니다.' );
		return;
	}

	if( nOrderSrl.length == 0 )
	{
		alert( '잘못된 주문번호입니다.' );
		return;
	}
	var params = new Array();
	params['order_srl'] = nOrderSrl;
	var response = ['is_changeable'];
	exec_xml('svorder', 'getSvorderUpdateAddrAllowable', params, function(ret_obj){
		if( ret_obj['is_changeable'] == '1' )
		{
			var $oTdDelivMemo = jQuery('[data-cart-srl='+nCartSrl+']').parent();
			$oTdDelivMemo.children('#txt_update_deliv_memo').show();
			$oTdDelivMemo.children('.btn_update_delivery_memo').show();
			$oTdDelivMemo.children('.btn_toggle_delivery_memo').html('숨기기');
			//jQuery('[data-cart-srl='+nCartSrl+']').html('숨기기');
		}
	}, response);
}

function _checkAddrChangeable( nOrderSrl )
{
	if( typeof nOrderSrl == 'undefined' )
	{
		alert( '잘못된 주문번호입니다.' );
		return;
	}

	if( nOrderSrl.length == 0 )
	{
		alert( '잘못된 주문번호입니다.' );
		return;
	}
	var params = new Array();
	params['order_srl'] = nOrderSrl;
	var response = ['is_changeable'];
	exec_xml('svorder', 'getSvorderUpdateAddrAllowable', params, function(ret_obj){
		if( ret_obj['is_changeable'] == '1' )
		{
			jQuery('#btn_show_addr').html('숨기기');
			jQuery('#tbl_postcodify_addr').show();
		}
	}, response);
}

function updateOrderStatus( nOrderSrl )
{
	if( typeof nOrderSrl == 'undefined' )
	{
		alert( '잘못된 주문번호입니다.' );
		return;
	}

	if( nOrderSrl.length == 0 )
	{
		alert( '잘못된 주문번호입니다.' );
		return;
	}
	
	var sOriginalOrderStatus = jQuery('#target_order_status option:eq(0)').val();
	// select box ID로 접근하여 선택된 값 읽기
	var sTargetOrderStatus = jQuery('#target_order_status option:selected').val();
	
	if( sOriginalOrderStatus == sTargetOrderStatus )
	{
		alert( '변경할 상태가 원래 상태와 동일합니다.' );
		return;
	}

	sDetailReason = jQuery('#detail_reason').val();
	sCancelReasonCode = jQuery('#cancel_reason').val(); // cancelled
	sCancelReqReasonCode = jQuery('#cancel_req_reason').val(); // cancel_request
	sRefundBankName = jQuery('#refund_bank_name').val(); // cancel_request
	sRefundBankAccount = jQuery('#refund_bank_account').val(); // cancel_request
	sRefundAccountHolder = jQuery('#refund_account_holder').val(); // cancel_request

	var params = new Array();
	params['order_srl'] = nOrderSrl;
	params['target_order_status'] = sTargetOrderStatus;
	params['detail_reason'] = sDetailReason;
	params['cancel_reason_code'] = sCancelReasonCode; // cancelled
	params['cancel_req_reason'] = sCancelReqReasonCode; // cancel_request
	params['refund_bank_name'] = sRefundBankName; // cancel_request
	params['refund_bank_account'] = sRefundBankAccount; // cancel_request
	params['refund_account_holder'] = sRefundAccountHolder; // cancel_request
	exec_xml('svorder', 'procSvorderUpdateOrderStatus', params, function(ret_obj){
		alert(ret_obj['message']);
// Google Analytics Code Begin (3/4/2016 9:48 AM) singleview.co.kr --->
// 목표 상태가 결제 취소면 gatk 환불 코드 실행
//		gatkMypage.refund( nOrderSrl );
//		gatkHeader.close();
// Google Analytics Code End (3/4/2016 9:48 AM) singleview.co.kr --->
		location.reload();
	});
}

function number_format(nStr)
{
    nStr += '';
    x = nStr.split('.');
    x1 = x[0];
    x2 = x.length > 1 ? '.' + x[1] : '';
    var rgx = /(\d+)(\d{3})/;
    while (rgx.test(x1)) {
        x1 = x1.replace(rgx, '$1' + ',' + '$2');
    }
    return x1 + x2;
}