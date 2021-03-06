(function($) {
	jQuery(function($) {
		// view order info.
		/*$('a.modalAnchor.viewOrderInfo').bind('before-open.mw', function(event){
			// get cart_srl
			var order_srl = $(this).attr('data-order-srl');

			// get enrollment form
			exec_xml(
				'svcart'
				, 'getSvcartAdminOrderDetails'
				, {order_srl : order_srl}
				, function(ret) {
					var tpl = ret.tpl.replace(/<enter>/g, '\n');
					console.log(tpl);
					$('#orderInfo').html(tpl); }
				, ['error','message','tpl']
			);
		});
		$('a.modalAnchor.deleteOrders').bind('before-open.mw', function(event){
			// get checked items.
			var a = [];
			var $checked_list = jQuery('input[name=cart\\[\\]]:checked');
			$checked_list.each(function() { a.push(jQuery(this).val()); });
			var order_srl = a.join(',');

			// get delete form.
			exec_xml(
					'svorder',
					'getSvorderAdminDeleteOrders',
					{order_srl:order_srl},
					function(ret){
							var tpl = ret.tpl.replace(/<enter>/g, '\n');
							$('#deleteForm').html(tpl);
					},
					['error','message','tpl']
			);
		});*/
		$('a.modalAnchor.modifyDataFormat').bind('before-open.mw', function(event){
			var item_srl = $(event.target).parent().attr('data-item-srl');
			//var checked = $(event.target).closest('tr').find('input:radio:checked').val();
			exec_xml(
				'svorder',
				'getSvorderAdminModifyDataFormat',
				{item_srl:item_srl},
				function(ret){
					var tpl = ret.tpl.replace(/<enter>/g, '\n');
					$('#CsvFormatForm').html(tpl);
				},
				['error','message','tpl']
			);

		});
		$('a.modalAnchor.registerShippingSerial').bind('before-open.mw', function(event){
			var item_srl = $(event.target).parent().attr('data-item-srl');
			//var checked = $(event.target).closest('tr').find('input:radio:checked').val();
			exec_xml(
				'svorder',
				'getSvorderAdminRegisterShippingSerial',
				{item_srl:item_srl},
				function(ret){
					var tpl = ret.tpl.replace(/<enter>/g, '\n');
					$('#ShippingSerialForm').html(tpl);
				},
				['error','message','tpl']
			);

		});
	});
}) (jQuery);
