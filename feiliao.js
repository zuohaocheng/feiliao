$(function() {
    var $window = $(window),
    $document = $(document),
    loading = false,
    args = {},
    page = 0;
    
    $.each(location.search.substr(1).split('&'), function() {
	var t = this.split('='),
	k = decodeURIComponent(t[0]),
	v = decodeURIComponent(t[1]);
	args[k] = v;
    });
    if ('page' in args) {
	page = parseInt(args.page);
	delete args.page;
    }
    
    $window.scroll(function() {
	if (loading) {
	    return;
	}
	if (($document.height() - $window.height() - $document.scrollTop()) < 50) {
	    loading = true;
	    var getArgs = args;
	    ++page;
	    args.page = page;
	    $.get(location.pathname, getArgs, function(r) {
		$('tbody').append(r);
		loading = false;
	    }, 'text');
	}
    });
});