/*
 * jQuery UI Effects Drop 1.8.1
 *
 * Copyright (c) 2010 AUTHORS.txt (http://jqueryui.com/about)
 * Dual licensed under the MIT (MIT-LICENSE.txt)
 * and GPL (GPL-LICENSE.txt) licenses.
 *
 * http://docs.jquery.com/UI/Effects/Drop
 *
 * Depends:
 *	jquery.effects.core.js
 */

(function(c){c.effects.drop=function(d){return this.queue(function(){var a=c(this),h=["position","top","left","opacity"],e=c.effects.setMode(a,d.options.mode||"hide"),b=d.options.direction||"left";c.effects.save(a,h);a.show();c.effects.createWrapper(a);var f=b=="up"||b=="down"?"top":"left",b=b=="up"||b=="left"?"pos":"neg",g=d.options.distance||(f=="top"?a.outerHeight({margin:!0})/2:a.outerWidth({margin:!0})/2);e=="show"&&a.css("opacity",0).css(f,b=="pos"?-g:g);var i={opacity:e=="show"?1:0};i[f]=(e==
"show"?b=="pos"?"+=":"-=":b=="pos"?"-=":"+=")+g;a.animate(i,{queue:!1,duration:d.duration,easing:d.options.easing,complete:function(){e=="hide"&&a.hide();c.effects.restore(a,h);c.effects.removeWrapper(a);d.callback&&d.callback.apply(this,arguments);a.dequeue()}})})}})(jQuery);
