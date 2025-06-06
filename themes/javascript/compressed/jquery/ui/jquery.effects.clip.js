/*
 * jQuery UI Effects Clip 1.8.1
 *
 * Copyright (c) 2010 AUTHORS.txt (http://jqueryui.com/about)
 * Dual licensed under the MIT (MIT-LICENSE.txt)
 * and GPL (GPL-LICENSE.txt) licenses.
 *
 * http://docs.jquery.com/UI/Effects/Clip
 *
 * Depends:
 *	jquery.effects.core.js
 */

(function(b){b.effects.clip=function(e){return this.queue(function(){var a=b(this),i=["position","top","left","height","width"],f=b.effects.setMode(a,e.options.mode||"hide"),c=e.options.direction||"vertical";b.effects.save(a,i);a.show();var d=b.effects.createWrapper(a).css({overflow:"hidden"}),d=a[0].tagName=="IMG"?d:a,g={size:c=="vertical"?"height":"width",position:c=="vertical"?"top":"left"},c=c=="vertical"?d.height():d.width();f=="show"&&(d.css(g.size,0),d.css(g.position,c/2));var h={};h[g.size]=
f=="show"?c:0;h[g.position]=f=="show"?0:c/2;d.animate(h,{queue:!1,duration:e.duration,easing:e.options.easing,complete:function(){f=="hide"&&a.hide();b.effects.restore(a,i);b.effects.removeWrapper(a);e.callback&&e.callback.apply(a[0],arguments);a.dequeue()}})})}})(jQuery);
