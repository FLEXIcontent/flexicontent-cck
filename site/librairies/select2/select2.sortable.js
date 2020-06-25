(function($){
	$.fn.extend({
		select2_sortable: function(options)
		{
			var select = $(this);

			options = typeof options !== 'undefined' ? options : {};
			options.items     = typeof options.items !== 'undefined' ? options.items : 'li:not(.select2-search-field)';
			options.tolerance = typeof options.tolerance !== 'undefined' ? options.tolerance : 'pointer';

			options.stop = typeof options.stop !== 'undefined' ? options.stop : function(event, ui)
			{
				$($(ul).find('.select2-search-choice').get().reverse()).each(function() {
					var id = $(this).data('select2Data').id;
					var option = select.find('option[value="' + id + '"]')[0];
					select.prepend(option);
				});
			}

			var ul = select.prev('.select2-container').first('ul');
			ul.sortable(options);
		}
	});
}(jQuery));
