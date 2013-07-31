
var noobSlide=new Class({initialize:function(params){this.items=params.items;this.mode=params.mode||'horizontal';this.fade=params.fade||false;this.modes={horizontal:['left','width'],vertical:['top','height']};this.size=params.size||240;this.box=params.box.setStyle(this.modes[this.mode][1],(this.size*this.items.length)+'px');this.button_event=params.button_event||'click';this.handle_event=params.handle_event||'click';this.onWalk=params.onWalk||null;this.currentIndex=null;this.previousIndex=null;this.nextIndex=null;this.interval=params.interval||5000;this.autoPlay=params.autoPlay||false;this._play=null;this.handles=params.handles||null;if(this.handles){this.addHandleButtons(this.handles);}
this.buttons={previous:[],next:[],play:[],playback:[],stop:[]};if(params.addButtons){for(var action in params.addButtons){this.addActionButtons(action,$type(params.addButtons[action])=='array'?params.addButtons[action]:[params.addButtons[action]]);}}
if(this.fade)
{this.orderItems()
this.fading((params.startItem||0),true,true);}
else
{this.fx=new Fx.Tween(this.box,$extend((params.fxOptions||{duration:500,wait:false}),{property:this.modes[this.mode][0]}));this.walk((params.startItem||0),true,true);}},orderItems:function(){for(i=0;i<this.items.length;i++)
{this.items[i].setStyle('position','absolute')
this.items[i].setStyle('left','0px');this.items[i].setStyle('z-index',i+1);if(i>0)
{this.items[i].fade('out');}}},addHandleButtons:function(handles){for(var i=0;i<handles.length;i++){if(this.fade)
{handles[i].addEvent(this.handle_event,this.fading.pass([i,true],this));}
else
{handles[i].addEvent(this.handle_event,this.walk.pass([i,true],this));}}},addActionButtons:function(action,buttons){for(var i=0;i<buttons.length;i++){switch(action){case'previous':buttons[i].addEvent(this.button_event,this.previous.pass([true],this));break;case'next':buttons[i].addEvent(this.button_event,this.next.pass([true],this));break;case'play':buttons[i].addEvent(this.button_event,this.play.pass([this.interval,'next',false],this));break;case'playback':buttons[i].addEvent(this.button_event,this.play.pass([this.interval,'previous',false],this));break;case'stop':buttons[i].addEvent(this.button_event,this.stop.create({bind:this}));break;}
this.buttons[action].push(buttons[i]);}},previous:function(manual){if(this.fade)
{this.fading((this.currentIndex>0?this.currentIndex-1:this.items.length-1),manual);}
else
{this.walk((this.currentIndex>0?this.currentIndex-1:this.items.length-1),manual);}},next:function(manual){if(this.fade)
{this.fading((this.currentIndex<this.items.length-1?this.currentIndex+1:0),manual);}
else
{this.walk((this.currentIndex<this.items.length-1?this.currentIndex+1:0),manual);}},play:function(interval,direction,wait){this.stop();if(!wait){this[direction](false);}
this._play=this[direction].periodical(interval,this,[false]);},stop:function(){$clear(this._play);},walk:function(item,manual,noFx){if(item!=this.currentIndex){this.currentIndex=item;this.previousIndex=this.currentIndex+(this.currentIndex>0?-1:this.items.length-1);this.nextIndex=this.currentIndex+(this.currentIndex<this.items.length-1?1:1-this.items.length);if(manual){this.stop();}
if(noFx){this.fx.cancel().set((this.size*-this.currentIndex)+'px');}else{this.fx.start(this.size*-this.currentIndex);}
if(manual&&this.autoPlay){this.play(this.interval,'next',true);}
if(this.onWalk){this.onWalk((this.items[this.currentIndex]||null),(this.handles&&this.handles[this.currentIndex]?this.handles[this.currentIndex]:null));}}},fading:function(item,manual,noFx){if(item!=this.currentIndex){this.lastIndex=this.currentIndex;this.currentIndex=item;this.previousIndex=this.currentIndex+(this.currentIndex>0?-1:this.items.length-1);this.nextIndex=this.currentIndex+(this.currentIndex<this.items.length-1?1:1-this.items.length);if(manual){this.stop();}
if(!noFx){this.items[this.lastIndex].fade('out');this.items[this.currentIndex].fade('in');}
if(manual&&this.autoPlay){this.play(this.interval,'next',true);}
if(this.onWalk){this.onWalk((this.items[this.currentIndex]||null),(this.handles&&this.handles[this.currentIndex]?this.handles[this.currentIndex]:null));}}}});