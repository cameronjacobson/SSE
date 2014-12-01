(function (global) {
	"use strict";

	if("EventSource2" in global) return;

	var EventSource2 = function(url,config){

		var urlstring = url;
		var url = function(){
			var l = document.createElement('a');
			l.href = url;
			return {
				origin: l.origin,
				host: l.host,
				hostname: l.hostname,
				href: l.href,
				hash: l.hash,
				pathname: l.pathname,
				port: l.port,
				protocol: l.protocol,
				search: l.search
			}
		}()

		var TRIES = 0;
		var TIME_STARTED = parseInt(new Date().getTime()/1000);
		var XHR_TIMEOUT = 3000;
		var RECONNECT_TIME = 2000;
		// FAILURES ALLOWED PER MINUTE BEFORE ABORT
		var FAILURES_PER_MINUTE = 5;

		var CONNECTING = 0;
		var OPEN = 1;
		var CLOSED = 2;
		var ABORTED = false;

		var readyState = null;
		var listeners = {};
		var last_event_id = 0;
		var responseTextOffset = 0;

		Object.defineProperty(this,'readyState',{
			get: function(){
				return readyState;
			},
			set: function(value){
				return false;
			}
		});
		Object.defineProperty(this,'close',{
			get: function(){
				return function(){
					ABORTED = true;
					xhr.abort();
				}
			},
			set: function(value){
				return false;
			}
		});
		Object.defineProperty(this,'url',{
			get: function(){
				return urlstring;
			},
			set: function(value){
				return false;
			}
		});

		Object.defineProperty(this,'onopen',{
			get: function(){
				return listeners['open'] || function(){};
			},
			set: function(fn){
				if(typeof fn == 'function'){
					listeners['open'] = fn;
				}
			}
		});

		Object.defineProperty(this,'onerror',{
			get: function(){
				return listeners['error'] || function(){};
			},
			set: function(fn){
				if(typeof fn == 'function'){
					listeners['error'] = fn;
				}
			}
		});

		Object.defineProperty(this,'onmessage',{
			get: function(){
				return listeners['message'] || function(){};
			},
			set: function(fn){
				if(typeof fn == 'function'){
					listeners['message'] = fn;
				}
			}
		});

		this.addEventListener = function(type, fn){
			listeners[type] = listeners[type] || [];
			listeners[type].push(fn);
		}

		this.removeEventListener = function(type, fn){
			if(listeners && listeners[type]){
				listeners[type].map(function(v,k,arr){
					if(arr[k] === v){
						arr.splice(k,1);
					}
				});
			}
		}

		var Event = function(){
			this.data = '';
		}

		var processStack = function(stack){
			var id = '', ev = '', message = '', data = '';
			var rctime = 0;

			stack.map(function(v,k,arr){
				var v = v.split(':');
				var key = v.shift();
				var value = v.join(':');
				switch(key){
					case 'id':
						last_event_id = parseInt(value);
						break;
					case 'event':
						ev = value.trim();
						break;
					case 'data':
						data += value.trim();
						break;
					case 'retry':
						if((rctime = parseInt(value)) > 0){
							RECONNECT_TIME = rctime;
						}
						break;
				}
			});
			callListeners(ev,data);
		}

		var readyStateChange = function(e){
			if(this.readyState === 3){
				this.LOADED = true;
				FAILURES_PER_MINUTE = 5;
			}
			if((this.readyState == 4) && (this.status == 200)){
				this.LOADED = false;
				//xhrClosed();
			}
		}

		var xhrProgress = function(e){
			var responseText = this.responseText.substring(responseTextOffset).split('\n');
			var line = '';
			var stack = [];
			var progress = 0;
			var offset = 0;

			responseText.map(function(v,k,arr){
				offset += v.length+1;
				if((v === "") && (stack.length > 0)){
					processStack(stack);
					responseTextOffset += offset;
					offset = 0;
					stack = [];
				}
				else if(v === ""){/* do nothing */}
				else{
					stack.push(v);
				}
			});
		}

		var eventsource_connect = function(){
			TRIES++;
			var MINUTES = parseInt(((new Date().getTime() / 1000) - TIME_STARTED) / 60);
			if(TRIES > 100 || (MINUTES >= 1 && (TRIES / MINUTES > FAILURES_PER_MINUTE))){
				console.log('TOO MANY FAILURES: RECONNECT TIME IS NOW 60 SECONDS');
				RECONNECT_TIME = 60000;
			}
			responseTextOffset = 0;
			var xhr = new XMLHttpRequest();
			readyState = CONNECTING;
			xhr.onreadystatechange = readyStateChange.bind(xhr);
			xhr.onloadstart = xhrLoadStart.bind(xhr);
			xhr.onloadend = xhrLoadEnd.bind(xhr);
			xhr.onerror = xhrError.bind(xhr);
			xhr.onload = xhrLoad.bind(xhr);
			xhr.onabort = xhrAbort.bind(xhr);
			xhr.open('get', urlstring, true);



			if(false){
				if(!/(Trident|MSIE)/.test(navigator.userAgent)){
					//xhr.responseType = '';
					window.setTimeout(function(){
						if(!this.STARTED || !this.LOADED){
							this.abort();
							readyState = CLOSED;
							//callListeners('error',{type:"timeout"});
							if(!ABORTED){
								startEventSource();
							}
						}
					}.bind(xhr),XHR_TIMEOUT);
				}
			}

			xhr.onprogress = xhrProgress.bind(xhr);

			if(last_event_id > 0){
				xhr.setRequestHeader("Last-Event-ID", last_event_id);
			}
			xhr.setRequestHeader('Cache-Control','no-cache');

			readyState = OPEN;
			xhr.send(null);
			window.setTimeout(function(){
				callListeners('open');
			}.bind(this),0);
		}.bind(this);

		var startEventSource = function(){
			window.setTimeout(function(){
				eventsource_connect();
			},RECONNECT_TIME);
		}

		var xhrLoadStart = function(e){
			this.STARTED = true;
		}

		var xhrLoadEnd = function(e){
			if(e.type !== 'loadend'){
				return;
			}
/*
			readyState = CLOSED;
			callListeners('error',e);
			if(!ABORTED){
				startEventSource();
			}
*/
		}

		var xhrAbort = function(e){
		}

		var xhrLoad = function(e){
			//this.LOADED = true;
			readyState = CLOSED;
			callListeners('error',e);
			if(!ABORTED){
				startEventSource();
			}
		}

		var xhrError = function(e){
			readyState = CLOSED;
			callListeners('error',e);
			if(!ABORTED){
				startEventSource();
			}
		}

		var xhrTimeout = function(e){
			readyState = CLOSED;
			callListeners('error',e);
			if(!ABORTED){
				startEventSource();
			}
		}

		var xhrClosed = function(){
			readyState = CLOSED;
			callListeners('error',{type:"closed"});
			if(!ABORTED){
				startEventSource();
			}
		}

		eventsource_connect();

		var callListeners = function(type,data){
			if(listeners && listeners[type]){
				var e = new Event();
				try{
					e.data = typeof data === 'string' ? JSON.parse(data) : data;
				}
				catch(ev){
					console.log(ev);
					e.data = '';
				}
				if(typeof listeners[type] === 'function'){
					listeners[type](e);
					return;
				}
				listeners[type].map(function(v){
					v(e);
				});
			}
		}
		return this;
	}
	global.EventSource2 = EventSource2;
}(this));



