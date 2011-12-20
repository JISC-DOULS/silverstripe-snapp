//Import into gadgets to connect to SS gadget web service controller

/**
 * SilverStripe Gadget "class" Connections to SS Gadget services Example: var sg =
 * new ss_gadget("http://localhost/silverstripe");
 */
var _ssg = null;//Internal pointer to silverstripe_gadget instance - use this when scope is an issue

function silverstripe_gadget(baseurl, dev) {
    if (_ssg == null) {
        _ssg = this;
    }
    if (typeof dev == 'undefined') {
        dev = false;
    }

    this.baseurl = baseurl;

    this.dev = dev;

    this.usermap = false;// Is there a user 'mapping' to silverstripe? Set by user_map_exist();

    this.user_map_exists = function() {

        if (_ssg.usermap) {
            return;// Already done
        }
        this.web_service_call(
            'GadgetWeb',
            'mapUser',
            '',
            function(ret)  {
                // Checks what silverstripe returns
                if (typeof ret.status == 'undefined' || ret.status != 200) {
                    //general network error (often happens in igoogle as bug where auth token expires)
                    var stringy = 'There was a network error when trying to get information for the gadget. Please refresh the page to try again.';
                    error(stringy);
                    return;
                }
                if (typeof ret.content != 'undefined' && typeof ret.content.response != 'undefined') {
                    ret.content = ret.content.response;
                }
                // Debugging info
                if (typeof ret.content != 'undefined' && typeof ret.content.debuginfo != 'undefined') {
                    debug(ret.content.debuginfo);
                }
                if (typeof ret.content != 'undefined' && typeof ret.content.userexists != 'undefined') {
                    if (ret.content.userexists) {
                        _ssg.usermap = true;
                        return;// Simple -user exists so stop
                    }
                }
                // Not mapped, so check for any errors first
                if (typeof ret.error != 'undefined') {
                    // General error
                    error(ret.error);
                    return;
                }
                if (typeof ret.content != 'undefined' && typeof ret.content.message != 'undefined') {
                    // Error from silverstripe
                    error(ret.content.message);
                    return;
                }
                // Everything OK - but no mapping - give user instructions
                YUI().use('node', function(Y) {
                    var um = Y.one('#usermap');// allow user defined div
                    if (!um) {
                        Y.one('body').prepend('<div id="usermap"> </div>');
                    }
                    var message = '<p>' + ret.content.instructions;
                    if (typeof ret.content.url != 'undefined') {
                        message += '<a id="usermap_link" href="'+ret.content.url+'">'+ ret.content.linktext +'</a>.';
                    }
                    message += '</p>';
                    if (um.get('innerHTML').indexOf(ret.content.instructions) == -1) {
                        //only write once
                        um.set('innerHTML', message);
                        var linky = Y.one('#usermap_link');
                        linky.on('click', function(e) {
                            window.open(this.get('href'), '_blank');
                            e.preventDefault();
                            //Keep checking by making more calls to this function
                            YUI().use('async-queue', function(Y) {
                                var q = new Y.AsyncQueue({
                                    fn:function() {_ssg.user_map_exists();}
                                    });
                                q.defaults.iterations = 500;
                                q.defaults.timeout = 3000;
                                q.defaults.until = function() {
                                    if (_ssg.usermap) {
                                        q.stop();
                                        YUI().use('node', function(Y) {
                                            Y.one('#usermap').setStyle('display', 'none');
                                        });
                                        gadgets.util.runOnLoadHandlers();
                                        return true;
                                    }
                                    return false;
                                };
                                q.run();
                            });
                        });
                    }
                });
            }
        );
    };

    /**
     * Call a silverstripe webservice as defined in wsfunction wsparams is a string of
     * what params you would normally send to the web service callback is the
     * function you what to send the result to cache is optional - set to true
     * to use a cached call
     * wsparams should be in format '?blah=asas&something=opop'
     */
    this.web_service_call = function(wservice, wsfunction, wsparams, callback, cache) {

        //wsparams = escape(wsparams);
        //After brute escape replace back ? and =
        //wsparams = wsparams.replace("%3F", "?");
        //wsparams = wsparams.replace("%3D", "=");

        var url = '/' + wservice + '/' + wsfunction + wsparams;

        /**
         * Function used to sit between webservice result and final callback
         * Will create UI if any errors sent from silverstripe
         */
        var wscallback = function(ret) {
            var haserror = "";
            //Make sure response always live in content
            if (typeof ret.content != 'undefined' && typeof ret.content.response != 'undefined') {
                ret.content = ret.content.response;
            }
            // If errors from call do not call callback; show error instead
            if (typeof ret.error != 'undefined') {
                // General error
                haserror = ret.error;
            } else if (typeof ret.content != 'undefined' &&
                    (typeof ret.content.message != 'undefined' || typeof ret.content.error != 'undefined')) {
                // Error from silverstripe
                haserror = ret.content.message;
            } else if (typeof ret.status == 'undefined' || ret.status != 200) {
                //general network error (often happens in igoogle as bug where auth token expires)
                var stringy = 'There was a network error when trying to get information for the gadget. Please refresh the page to try again.';
                haserror = stringy;
            }
            if (haserror == "") {
                //check for ajax call errors (severe error picked up already by default)
                callback(ret);
            } else {
                //if (!_ssg.dev) {
                    error(haserror);
                //} else {
                    //In dev mode fallback to ajax call
                //    _ssg.call_silverstripe(url, wscallback, 0, true);
                //}
            }
        };

        this.call_silverstripe(url, wscallback, cache);
    };

    /**
     * Call a silverstripe url with a OAuth signed request (not cached)
     */
    this.call_silverstripe = function(rurl, callback, cache, ajax) {
        if (typeof cache == 'undefined') {
            cache = false;
        }
        if (typeof ajax == 'undefined') {
            ajax = false;
        }

        // check relative url has fwdslash
        if (rurl.charAt(0) != '/') {
            rurl = '/' + rurl;
        }

        var filebase = "/snappjsonservice";
        if (this.baseurl.search("open.ac.uk") != -1) {
            filebase = '/snapp/ws/call.php/snappjsonservice';
        }
        var params = {
                'href' : this.baseurl + filebase + rurl,
                'format' : 'json',
                'authz' : 'signed'
           };
        if (!cache) {
           params.refreshInterval = 0;
        }

        if (!ajax && !_ssg.dev) {
                osapi.http.get(params).execute(callback);
        } else {
            //Do a cors ajax call - this will only work in a few browsers e.g. chrome
            //This is useful so that you can log into the system, and view gadget without a mapping

            var requesty = create_cors_request('GET', params.href);
            if (requesty) {
                requesty.onload = function(e) {
                    try {
                        //use yui to turn json into object that matchs osapi return
                        var response = this.response;
                        YUI().use('json-parse', function (Y) {
                            var data = Y.JSON.parse(response);
                            if (data.response) {
                                requesty.content = data.response;
                            } else {
                                requesty.content = data;
                            }
                            callback(requesty);
                        });
                    } catch (e) {
                        this.content = {};
                        callback(this);
                    }
                };
                requesty.onerror = function(e) {
                    error("Sorry, there was an error whilst contacting the server. " +
                    "Ensure you are signed in.");
                };
                requesty.withCredentials = 'true';
                requesty.send();
            } else {
                //Use JSON-P as last resort (mainly for IE)
                YUI().use('jsonp', function (Y) {
                    var theurl = params.href;
                    if (theurl.indexOf("?") != -1) {
                        params.href = params.href + '&callback={callback}';
                    } else {
                        params.href = params.href + '?callback={callback}';
                    }
                    Y.jsonp(params.href, function (ret){
                        if (typeof ret != "object") {
                            error("Sorry, there was an error whilst contacting the server. " +
                            "Ensure you are signed in.");
                        }
                        var toret = {"status":200};
                        if (ret.response) {
                            toret.content = ret.response;
                        } else {
                            toret.content = ret;
                        }
                        callback(toret);
                        }
                    );
                });
            }
        }
    };
}


function create_cors_request(method, url) {
    var xhr = null;
    try {
        if (window.XMLHttpRequest) {
            xhr = new XMLHttpRequest();
            if ("withCredentials" in xhr) {
                xhr.open(method, url, true);
            } else {
                xhr = null;
            }
        } else {
            xhr = null;
        }
    } catch(e) {
        xhr = null;
    }
    return xhr;
}

function debug(content) {
    YUI().use('node','event-key','overlay', function(Y) {
        var debugnode = Y.one('#debug');
        if (!debugnode) {
            Y.one('body').append('<div id="debug">'+ content +'</div>');
            debugnode = Y.one('#debug');
            debugnode.setStyle('background-color', '#fff');
            debugnode.setStyle('display', 'none');
            var overlay = new Y.Overlay({
                srcNode:"#debug",
                visible:false,
                width:"40em"
            });
            overlay.render();
            Y.on('key', function(e) {
                // stopPropagation() and preventDefault()
                e.halt();
                overlay.set('visible', true);
                Y.one('#debug').setStyle('display', 'block');
            }, 'body', 'down:68', Y);
            Y.on('key', function(e) {
                // stopPropagation() and preventDefault()
                e.halt();
                overlay.set('visible', false);
                Y.one('#debug').setStyle('display', 'none');
            }, 'body', 'up:68', Y);
            return;
        }
        acontent = debugnode.get('innerHTML') + content;
        debugnode.set('innerHTML', acontent);
    });
}

function error(message) {
    YUI().use('node', 'yui2-resize', 'yui2-dragdrop', 'yui2-container', 'yui2-button',
            'yui2-layout', 'yui2-event', function(Y) {
        // Instantiate a Panel from script
        var errordiv = Y.one('#error');
        if (!errordiv) {
            Y.one('body').append('<div id="error"> </div>');
            Y.one('body').addClass('yui-skin-sam');
        } else {
            errordiv.set("innerHTML", "");
        }
        //YAHOO var doesn't seem to work use Y.YUI2 instead
        var errorpan = new Y.YUI2.widget.Panel("panel2", { width:"220px", visible:true,
            draggable:true, close:true, y:0, constraintoviewport:true } );
        errorpan.setHeader("Warning");
        errorpan.setBody(message);
        errorpan.render("error");
    });
}
