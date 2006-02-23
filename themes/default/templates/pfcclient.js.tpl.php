/**
 * This class is the client part of phpFreeChat
 * (depends on prototype library)
 * @author Stephane Gully
 */
var pfcClient = Class.create();

//defining the rest of the class implmentation
pfcClient.prototype = {
  
  initialize: function()
  {
    /* user description */
    this.nickname      = '';
    
    this.timeout       = null;
    this.refresh_delay = <?php echo $refresh_delay; ?>;
    /* unique client id for each windows used to identify a open window
     * this id is passed every time the JS communicate with server
     * (2 clients can use the same session: then only the nickname is shared) */
    this.clientid      = '<?php echo md5(uniqid(rand(), true)); ?>';

    this.minmax_status = <?php if ($start_minimized) { ?>true<?php } else { ?>false<?php } ?>;
    var cookie = getCookie('<?php echo $prefix; ?>minmax_status');
    if (cookie != null)
      this.minmax_status = (cookie == 'true');
    cookie = getCookie('<?php echo $prefix; ?>nickmarker');
    this.nickmarker = (cookie == 'true');
    if (cookie == '' || cookie == null)
      this.nickmarker = <?php if ($nickmarker) { ?>true<?php } else { ?>false<?php } ?>;
    
    cookie = getCookie('<?php echo $prefix; ?>clock');
    this.clock = (cookie == 'true');
    if (cookie == '' || cookie == null)
      this.clock = <?php if ($clock) { ?>true<?php } else { ?>false<?php } ?>;

    cookie = getCookie('<?php echo $prefix; ?>showSmileys');
    this.showSmileys = (cookie == 'true');
    if (cookie == '')
      this.showSmileys = <?php if ($showsmileys) { ?>true<?php } else { ?>false<?php } ?>;

    cookie = getCookie('<?php echo $prefix; ?>showWhosOnline');
    this.showWhosOnline = (cookie == 'true');
    if (cookie == '')
      this.showWhosOnline = <?php if ($showwhosonline) { ?>true<?php } else { ?>false<?php } ?>;      
             
    this.login_status  = false; // todo: initialize this variable with the cookie value
    this.nicklist      = Array();
    this.nickcolor     = Array();
    this.colorlist     = Array();

    this.blinktmp     = Array();
    this.blinkloop    = Array();
    this.blinktimeout = Array();

    this.el_words     = $('<?php echo $prefix; ?>words');
    this.el_handle    = $('<?php echo $prefix; ?>handle');
    this.el_container = $('<?php echo $prefix; ?>container');
    this.el_online    = $('<?php echo $prefix; ?>online');
    this.el_errors    = $('<?php echo $prefix; ?>errors');

    /* the events callbacks */
    this.el_words.onkeydown  = this.callbackWords_OnKeydown.bindAsEventListener(this);
    this.el_words.onfocus    = this.callbackWords_OnFocus.bindAsEventListener(this);
    this.el_handle.onkeydown = this.callbackHandle_OnKeydown.bindAsEventListener(this);
    this.el_handle.onchange  = this.callbackHandle_OnChange.bindAsEventListener(this);
    this.el_container.onmousemove = this.callbackContainer_OnMousemove.bindAsEventListener(this);
    this.el_container.onmousedown = this.callbackContainer_OnMousedown.bindAsEventListener(this);
    this.el_container.onmouseup   = this.callbackContainer_OnMouseup.bindAsEventListener(this);
    document.body.onunload = this.callback_OnUnload.bindAsEventListener(this);

    /* the i18n translations */
    var i18n = {
      hide_nickname_color: '<?php echo _pfc("Hide nickname marker"); ?>',
      show_nickname_color: '<?php echo _pfc("Show nickname marker"); ?>',
      hide_clock:          '<?php echo _pfc("Hide dates and hours"); ?>',
      show_clock:          '<?php echo _pfc("Show dates and hours"); ?>',
      logout:              '<?php echo _pfc("Disconnect"); ?>',
      login:               '<?php echo _pfc("Connect"); ?>',
      maximize:            '<?php echo _pfc("Magnify"); ?>',
      minimize:            '<?php echo _pfc("Cut down"); ?>',
      hidesmiley:          '<?php echo _pfc("Hide smiley box"); ?>',
      showsmiley:          '<?php echo _pfc("Show smiley box"); ?>',
      hideonline:          '<?php echo _pfc("Hide online users box"); ?>',
      showonline:          '<?php echo _pfc("Show online users box"); ?>'
    };
    this.i18n = $H(i18n);

    /* the smileys */
    var smileys = {
      <?php
      $output = "";
      foreach($smileys as $s_file => $s_str) { 
	for($j = 0; $j<count($s_str) ; $j++) {
	  $s = $s_str[$j];
	  $output .= "'".$s."': '".$s_file."',";
	}
      }
      $output = substr($output, 0, strlen($output)-1); // remove last ','
      echo $output;
      ?>
    }
    //    this.smileys = ;
    //var k = this.smileys.keys(); alert(k.inspect());
    this.smileys = $H(smileys);
    //    this.smileyparser = new pfcSmileyParser($H(smileys));
  },
  
  callbackWords_OnKeydown: function(evt)
  {
    if (!this.login_status) return false;
    this.clearError(Array(this.el_words));
    var code = evt.keyCode;
    if (code == 13) /* enter key */
    {
      var w = this.el_words;
      var wval = w.value;
      re = new RegExp("^(\/[a-z]+)( (.*)|)");
      if (wval.match(re))
      {
	/* a user command */
	cmd   = wval.replace(re, '$1');
	param = wval.replace(re, '$2');
	this.handleRequest(cmd, param.substr(0,<?php echo $max_text_len; ?> + this.clientid.length));
      }
      else
      {
	/* a classic 'send' command*/
	this.handleRequest('/send', wval.substr(0,<?php echo $max_text_len; ?>));
      }
      w.value = '';
      return false;
    }
    else if (code == 39) /* right direction */
    {
      var w = this.el_words;
      var nick_src = w.value.substring(w.value.lastIndexOf(' ')+1,w.value.length);
      if (nick_src != '')
      {
	var ul_online = this.el_online.firstChild;
	for (var i=0; i<ul_online.childNodes.length; i++)
	{
	  var nick = ul_online.childNodes[i].innerHTML;
	  if (nick.indexOf(nick_src) == 0)
	    w.value = w.value.replace(nick_src, nick);
	}
      }
    }
    else
    {
    }
  },
  callbackWords_OnFocus: function(evt)
  {
    if (this.el_handle && this.el_handle.value == '' && !this.minmax_status)
      this.el_handle.focus();
  },
  callbackHandle_OnKeydown: function(evt)
  {
  },
  callbackHandle_OnChange: function(evt)
  {
  },
  callback_OnUnload: function(evt)
  {
    /* don't disconnect users when they reload the window
     * this event doesn't only occurs when the page is closed but also when the page is reloaded */
    /*
    if (!this.login_status) return false;
    this.handleRequest('/quit');
    */
  },

  callbackContainer_OnMousemove: function(evt)
  {
    this.isdraging = true;
  },
  callbackContainer_OnMousedown: function(evt)
  {
    this.isdraging = false;
  },
  callbackContainer_OnMouseup: function(evt)
  {
    if (!this.isdraging)
      if (this.el_words && !this.minmax_status)
        this.el_words.focus();
  },
  callbackOnQuit: function(evt)
  {
    if (!this.login_status) return false;
    this.handleRequest('/quit');
  },

  /**
   * hide error area and stop blinking fields
   */
  clearError: function(ids)
  { 
    this.el_errors.style.display = 'none';
    for (var i=0; i<ids.length; i++)
      this.blink(ids[i].id, 'stop');
  },

  /**
   * show error area and assign to it an error message and start the blinking of given fields
   */
  setError: function(str, ids)
  {
    this.el_errors.innerHTML = str;
    this.el_errors.style.display = 'block';
    for (var i=0; i<ids.length; i++)
      this.blink(ids[i].id, 'start');
  },

  /**
   * blink routines used by Error functions
   */
  blink: function(id, action)
  {
    clearTimeout(this.blinktimeout[id]);
    if ($(id) == null) return;
    if (action == 'start')
    {
      this.blinktmp[id] = $(id).style.backgroundColor;
      clearTimeout(this.blinktimeout[id]);
      this.blinktimeout[id] = setTimeout('pfc.blink(\'' + id + '\',\'loop\')', 500);
    }
    if (action == 'stop')
    {
      $(id).style.backgroundColor = this.blinktmp[id];
    }
    if (action == 'loop')
    {
      if (this.blinkloop[id] == 1)
      {
	$(id).style.backgroundColor = '#FFDFC0';
	this.blinkloop[id] = 2;
      }
      else
      {
	$(id).style.backgroundColor = '#FFFFFF';
	this.blinkloop[id] = 1;
      }
      this.blinktimeout[id] = setTimeout('pfc.blink(\'' + id + '\',\'loop\')', 500);
    }
  },

  /**
   * Call the ajax request function
   * Will query the server
   */
  handleRequest: function(cmd, param)
  {
    <?php echo $prefix; ?>handleRequest(cmd + " " + this.clientid + (param ? " " + param : ""));
  },

  /**
   * update function to poll the server each 'refresh_delay' time
   */
  updateChat: function(start)
  {
    window.clearTimeout(this.timeout);
    if (start)
    {
      this.handleRequest('/update');
      this.timeout = window.setTimeout('pfc.updateChat(true)',
				       this.refresh_delay);
    }
  },

  /**
   * insert a smiley
   */
  insertSmiley: function(s)
  {
    this.el_words.value += s;
    this.el_words.focus();
  },

  /**
   * fill the nickname list with connected nicknames
   */
  updateNickList: function(lst)
  {
    this.nicklist = lst;
    var nicks   = lst;
    var nickdiv = this.el_online;
    var ul = document.createElement('ul');
    for (var i=0; i<nicks.length; i++)
    {
      var li = document.createElement('li');
      li.setAttribute('class', '<?php echo $prefix; ?>nickmarker <?php echo $prefix; ?>nick_'+ hex_md5(nicks[i]));
      var txt = document.createTextNode(nicks[i]);
      li.appendChild(txt);
      ul.appendChild(li);
    }
    var fc = nickdiv.firstChild;
    if (fc)
      nickdiv.replaceChild(ul,fc);
    else
      nickdiv.appendChild(ul,fc);
    this.colorizeNicks(nickdiv);
  },

  /**
   * clear the nickname list
   */
  clearNickList: function()
  {
    var nickdiv = this.el_online;
    var fc = nickdiv.firstChild;
    nickdiv.removeChild(fc);
  },


  /**
   * clear the message list history
   */
  clearMessages: function()
  {
    var msgdiv = $('<?php echo $prefix; ?>chat');
    msgdiv.innerHTML = '';
  },

  /**
   * parse the message
   */
  parseMessage: function(msg)
  {
    var rx = null;
    
    /* try to parse http adresses */
    rx = new RegExp('(http\:\/\/[^ ]*)','ig');
    msg = msg.replace(rx, '<a href="$1"<?php if($openlinknewwindow) echo ' target="_blank"'; ?>>$1</a>');

    /* try to parse bbcode */


    rx = new RegExp('\\[b\\](.+?)\\[\/b\\]','ig');
    msg = msg.replace(rx, '<span style="font-weight: bold">$1</span>');
    rx = new RegExp('\\[i\\](.+?)\\[\/i\\]','ig');
    msg = msg.replace(rx, '<span style="font-style: italic">$1</span>');
    rx = new RegExp('\\[u\\](.+?)\\[\/u\\]','ig');
    msg = msg.replace(rx, '<span style="text-decoration: underline">$1</span>');
    rx = new RegExp('\\[s\\](.+?)\\[\/s\\]','ig');
    msg = msg.replace(rx, '<span style="text-decoration: line-through">$1</span>');
    rx = new RegExp('\\[pre\\](.+?)\\[\/pre\\]','ig');
    msg = msg.replace(rx, '<pre>$1</pre>');  
    rx = new RegExp('\\[email\\]([^\[]*?)\\[\/email\\]','ig');
    msg = msg.replace(rx, '<a href="mailto:$1">$1</a>'); 
    rx = new RegExp('\\[email=([^\[]*?)\\](.*?)\\[\/email\\]','ig');
    msg = msg.replace(rx, '<a href="mailto:$1">$2</a>');
    rx = new RegExp('\\[color=([a-zA-Z]*|\\#?[0-9a-fA-F]{6})](.*?)\\[\/color\\]','ig');
    msg = msg.replace(rx, '<span style="color: $1">$2</span>');
      
    /* try to parse nickname for highlighting  */
    rx = new RegExp(RegExp.escape(this.nickname),'g');
    msg = msg.replace(rx, '<strong>'+ this.nickname +'</strong>');
    
    /* try to parse smileys */
    var sl = this.smileys.keys();
    for(var i = 0; i < sl.length; i++)
    {
      rx = new RegExp(RegExp.escape(sl[i]),'g');
      msg = msg.replace(rx, '<img src="'+ this.smileys[sl[i]] +'" alt="' + sl[i] + '" title="' + sl[i] + '" />');
    }

    
    return msg;
  },

  /**
   * parse messages and append it to the message list
   */
  parseAndPost: function(msgs)
  {
    var msgdiv = $('<?php echo $prefix; ?>chat');
    var msgids = Array();

    var html = '';
    for(var mid = 0; mid < msgs.length ; mid++)
    {      
      var id        = msgs[mid][0];
      var date      = msgs[mid][1];
      var heure     = msgs[mid][2];
      var nick      = msgs[mid][3];
      var words     = msgs[mid][4];
      var cmd       = msgs[mid][5];
      var fromtoday = msgs[mid][6];
      var oldmsg    = msgs[mid][7];

      msgids.push(id);
      //this.smileyparser.addMsgToParse(id);

      /* check the nickname is in the list or not */
      var nickfound = false;
      for(var i = 0; i < this.nicklist.length && !nickfound; i++)
      {
	if (this.nicklist[i] == nick)
	  nickfound = true;
      }
      var nickcolor = '';
      if (nickfound) nickcolor = this.getAndAssignNickColor(nick);

      /* format and post message */
      var line = '';
      line += '<div id="<?php echo $prefix; ?>msg'+ id +'" class="<?php echo $prefix; ?>'+ cmd +' <?php echo $prefix; ?>message';
      if (oldmsg == 1) line += ' <?php echo $prefix; ?>oldmsg';
      line += '">';
      line += '<span class="<?php echo $prefix; ?>date';
      if (fromtoday == 1) line += ' <?php echo $prefix; ?>invisible';
      line += '">'+ date +'</span> ';
      line += '<span class="<?php echo $prefix; ?>heure">'+ heure +'</span> ';
      if (cmd == 'cmd_msg')
      {
	line += ' <span class="<?php echo $prefix; ?>nick">';
	line += '&#x2039;';
	line += '<span ';
	if (nickcolor != '') line += 'style="color: ' + nickcolor + '" ';
	line += 'class="<?php echo $prefix; ?>nickmarker <?php echo $prefix; ?>nick_'+ hex_md5(nick) +'">';
	line += nick;
	line += '</span>';
	line += '&#x203A;';
	line += '</span> ';
      }
      if (cmd == 'cmd_notice' || cmd == 'cmd_me')
	line += '<span class="<?php echo $prefix; ?>words">* '+ this.parseMessage(words) +'</span> ';
      else
	line += '<span class="<?php echo $prefix; ?>words">'+ this.parseMessage(words) +'</span> ';
      line += '</div>';
      html += line;
    }

    /* create a dummy div to avoid konqueror bug when setting nickmarkers */
    var m = document.createElement('div');
    m.innerHTML = html;

    /* finaly append this to the message list */
    msgdiv.appendChild(m);
    
    for(var i = 0; i < msgids.length ; i++) 
    {
      this.scrolldown($('<?php echo $prefix; ?>msg'+ msgids[i]));
      /* colorize messages nicknames */
      var root = $('<?php echo $prefix; ?>msg'+ msgids[i]);
      this.refresh_nickmarker(root);
      this.refresh_clock(root);
      
        /* Daffys */
      this.refresh_Smileys();
      this.refresh_WhosOnline();
       /* Daffys */
    }
  },


  /**
   * scroll down from the posted message height
   */
  scrolldown: function(elttoscroll)
  {
    $('<?php echo $prefix; ?>chat').scrollTop += elttoscroll.offsetHeight+2;
  },

  /**
   * apply nicknames color to the root childs
   */
  colorizeNicks: function(root)
  {
    for(var i = 0; i < this.nicklist.length; i++)
    {
      var cur_nick = this.nicklist[i];
      var cur_color = this.getAndAssignNickColor(cur_nick);
      this.applyNickColor(root, cur_nick, cur_color);
    }
  },

  /**
   * Initialize the color array used to colirize the nicknames
   */
  reloadColorList: function()
  {
    this.colorlist = Array('#CCCCCC',
			   '#000000',
			   '#3636B2',
			   '#2A8C2A',
			   '#C33B3B',
			   '#C73232',
			   '#80267F',
			   '#66361F',
			   '#D9A641',
			   '#3DCC3D',
			   '#1A5555',
			   '#2F8C74',
			   '#4545E6',
			   '#B037B0',
			   '#4C4C4C',
			   '#959595'
			   );
  },
  

  /**
   * get the corresponding nickname color
   */
  getAndAssignNickColor: function(nick)
  {
    /* check the nickname is colorized or not */
    var allready_colorized = false;
    var nc = '';
    for(var j = 0; j < this.nickcolor.length; j++)
    {
      if (this.nickcolor[j][0] == nick)
      {
	allready_colorized = true;
	nc = this.nickcolor[j][1];
      }
    }
    if (!allready_colorized)
    {
      /* reload the color stack if it's empty */
      if (this.colorlist.length == 0) this.reloadColorList();
      /* take the next color from the list and colorize this nickname */
      var cid = Math.round(Math.random()*(this.colorlist.length-1));
      nc = this.colorlist[cid];
      this.colorlist.splice(cid,1);
      this.nickcolor.push(new Array(nick, nc));
    }
    return nc;
  },
  

  /**
   * Colorize with 'color' all the nicknames found as a 'root' child
   */
  applyNickColor: function(root, nick, color)
  {
    var nicktochange = this.getElementsByClassName(root, '<?php echo $prefix; ?>nick_'+ hex_md5(nick), '')
    for(var i = 0; nicktochange.length > i; i++)
      nicktochange[i].style.color = color; 
  },

  /**
   * Returns a list of elements which have a clsName class
   */
  getElementsByClassName: function( root, clsName, clsIgnore )
  {
    var i, matches = new Array();
    var els = root.getElementsByTagName('*');
    var rx1 = new RegExp('.*'+clsName+'.*');
    var rx2 = new RegExp('.*'+clsIgnore+'.*');
    for(i=0; i<els.length; i++) {
      if(els.item(i).className.match(rx1) &&
         (clsIgnore == '' || !els.item(i).className.match(rx2)) ) {
	matches.push(els.item(i));
      }
    }
    return matches;
  },

  showClass: function(root, clsName, clsIgnore, show)
  {
    var elts = this.getElementsByClassName(root, clsName, clsIgnore);
    for(var i = 0; elts.length > i; i++)
    if (show)
      elts[i].style.display = 'inline';
    else
      elts[i].style.display = 'none';
  },


  /**
   * Nickname marker show/hide
   */
  nickmarker_swap: function()
  {
    if (this.nickmarker) {
      this.nickmarker = false;
    } else {
      this.nickmarker = true;
    }
    this.refresh_nickmarker()
    setCookie('<?php echo $prefix; ?>nickmarker', this.nickmarker);
  },
  refresh_nickmarker: function(root)
  {
    var nickmarker_icon = $('<?php echo $prefix; ?>nickmarker');
    if (!root) root = $('<?php echo $prefix; ?>chat');
    if (this.nickmarker)
    {
      nickmarker_icon.src   = "<?php echo $c->getFileUrlFromTheme('images/color-on.gif'); ?>";
      nickmarker_icon.alt   = this.i18n.hide_nickname_color;
      nickmarker_icon.title = nickmarker_icon.alt;
      this.colorizeNicks(root);
      this.colorizeNicks($('<?php echo $prefix; ?>online'));
    }
    else
    {
      nickmarker_icon.src   = "<?php echo $c->getFileUrlFromTheme('images/color-off.gif'); ?>";
      nickmarker_icon.alt   = this.i18n.show_nickname_color;
      nickmarker_icon.title = nickmarker_icon.alt;
      var elts = this.getElementsByClassName(root, '<?php echo $prefix; ?>nickmarker', '');
      for(var i = 0; elts.length > i; i++)
      {
	/* this is not supported in konqueror =>>>  elts[i].removeAttribute('style');*/
	elts[i].style.color = '';
      }
      var elts = this.getElementsByClassName($('<?php echo $prefix; ?>online'), '<?php echo $prefix; ?>nickmarker', '');
      for(var i = 0; elts.length > i; i++)
      {
	/* this is not supported in konqueror =>>>  elts[i].removeAttribute('style');*/
	elts[i].style.color = '';
      }
    }
  },


  /**
   * Date/Hour show/hide
   */
  clock_swap: function()
  {
    if (this.clock) {
      this.clock = false;
    } else {
      this.clock = true;
    }
    this.refresh_clock();
    setCookie('<?php echo $prefix; ?>clock', this.clock);
  },
  refresh_clock: function( root )
  {
    var clock_icon = $('<?php echo $prefix; ?>clock');
    if (!root) root = $('<?php echo $prefix; ?>chat');
    if (this.clock)
    {
      clock_icon.src   = "<?php echo $c->getFileUrlFromTheme('images/clock-on.gif'); ?>";
      clock_icon.alt   = this.i18n.hide_clock;
      clock_icon.title = clock_icon.alt;
      this.showClass(root, '<?php echo $prefix; ?>date', '<?php echo $prefix; ?>invisible', true);
      this.showClass(root, '<?php echo $prefix; ?>heure', '<?php echo $prefix; ?>invisible', true);
    }
    else
    {
      clock_icon.src   = "<?php echo $c->getFileUrlFromTheme('images/clock-off.gif'); ?>";
      clock_icon.alt   = this.i18n.show_clock;
      clock_icon.title = clock_icon.alt;
      this.showClass(root, '<?php echo $prefix; ?>date', '<?php echo $prefix; ?>invisible', false);
      this.showClass(root, '<?php echo $prefix; ?>heure', '<?php echo $prefix; ?>invisible', false);
    }
    /* browser automaticaly scroll up misteriously when showing the dates */
    $('<?php echo $prefix; ?>chat').scrollTop += 30;
  },

  /**
   * Connect/disconnect button
   */
  connect_disconnect: function()
  {
    if (this.login_status)
    {
      this.handleRequest('/quit');
      this.login_status = false;
      this.clearNickList();
      this.clearMessages();
    }
    else
    {
      this.handleRequest('/connect');
      this.login_status = true;
      this.updateNickList(this.nicklist);
    }
    this.refresh_loginlogout()
  },
  refresh_loginlogout: function()
  {
    var loginlogout_icon = $('<?php echo $prefix; ?>loginlogout');
    if (this.login_status)
    {
      loginlogout_icon.src   = "<?php echo $c->getFileUrlFromTheme('images/logout.gif'); ?>";
      loginlogout_icon.alt   = this.i18n.logout;
      loginlogout_icon.title = loginlogout_icon.alt;
    }
    else
    {
      loginlogout_icon.src   = "<?php echo $c->getFileUrlFromTheme('images/login.gif'); ?>";
      loginlogout_icon.alt   = this.i18n.login;
      loginlogout_icon.title = loginlogout_icon.alt;
    }
  },



  /**
   * Minimize/Maximized the chat zone
   */
  swap_minimize_maximize: function()
  {
    if (this.minmax_status) {
      this.minmax_status = false;
    } else {
      this.minmax_status = true;
    }
    setCookie('<?php echo $prefix; ?>minmax_status', this.minmax_status);
    this.refresh_minimize_maximize();
  },
  refresh_minimize_maximize: function()
  {
    var content = $('<?php echo $prefix; ?>content_expandable');
    var btn     = $('<?php echo $prefix; ?>minmax');
    if (this.minmax_status)
    {
      btn.src = "<?php echo $c->getFileUrlFromTheme('images/maximize.gif'); ?>";
      btn.alt = this.i18n.maximize;
      btn.title = btn.alt;
      content.style.display = 'none';
    }
    else
    {
      btn.src = "<?php echo $c->getFileUrlFromTheme('images/minimize.gif'); ?>";
      btn.alt = this.i18n.minimize;
      btn.title = btn.alt;
      content.style.display = 'block';
    }
  },
  
  /**
   * BBcode ToolBar
   */
  insert_text: function(open, close) 
  {
    var msgfield = $('<?php echo $prefix; ?>words');
    
    // IE support
    if (document.selection && document.selection.createRange)
    {
      msgfield.focus();
      sel = document.selection.createRange();
      sel.text = open + sel.text + close;
      msgfield.focus();
    }
    
    // Moz support
    else if (msgfield.selectionStart || msgfield.selectionStart == '0')
    {
      var startPos = msgfield.selectionStart;
      var endPos = msgfield.selectionEnd;
      
      msgfield.value = msgfield.value.substring(0, startPos) + open + msgfield.value.substring(startPos, endPos) + close + msgfield.value.substring(endPos, msgfield.value.length);
      msgfield.selectionStart = msgfield.selectionEnd = endPos + open.length + close.length;
      msgfield.focus();
    }
    
    // Fallback support for other browsers
    else
    {
      msgfield.value += open + close;
      msgfield.focus();
    }
    return;
  },
  
  /**
   * Minimize/Maximize none/inline
   */
  minimize_maximize: function()
  {
    var element = $('<?php echo $prefix; ?>color');
    if(element.style)
    {
      if(element.style.display == 'inline' )
      {
        element.style.display = 'none';
      }
      else
      {
        element.style.display = 'inline';
      }
    }
  },
  
  
  /**
   * Smiley show/hide
   */
  showHideSmileys: function()
  {
    if (this.showSmileys)
    {
      this.showSmileys = false;
    }
    else
    {
      this.showSmileys = true;
    }
    setCookie('<?php echo $prefix; ?>showSmileys', this.showSmileys);
    this.refresh_Smileys();
  },
  refresh_Smileys: function()
  {
    var content = $('<?php echo $prefix; ?>smileys');
    var btn = $('<?php echo $prefix; ?>showHideSmileysbtn');
    
    if (this.showSmileys)
    {
      btn.src = "<?php echo $c->getFileUrlFromTheme('images/smiley-off.gif'); ?>";
      btn.alt = this.i18n.showsmiley;
      btn.title = btn.alt;
      content.style.display = 'none';
    }
    else
    {
      btn.src = "<?php echo $c->getFileUrlFromTheme('images/smiley-on.gif'); ?>";
      btn.alt = this.i18n.hidesmiley;
      btn.title = btn.alt;
      content.style.display = 'block';
    }
    this.refresh_Chat();
    this.refresh_OnlineAndSmileys();
  },
  
  
  /**
   * Show Hide who's online
   */
  showHideWhosOnline: function()
  {
    if (this.showWhosOnline)
    {
      this.showWhosOnline = false;
    }
    else
    {
      this.showWhosOnline = true;
    }
    setCookie('<?php echo $prefix; ?>showWhosOnline', this.showWhosOnline);
    this.refresh_WhosOnline();
  },
  refresh_WhosOnline: function()
  {
    var content = $('<?php echo $prefix; ?>online');
    var btn = $('<?php echo $prefix; ?>showHideWhosOnlineBtn');
    
    if (this.showWhosOnline)
    {
      btn.src = "<?php echo $c->getFileUrlFromTheme('images/online-off.gif'); ?>";
      btn.alt = this.i18n.showonline;
      btn.title = btn.alt;
      content.style.display = 'none';
    }
    else
    {
      btn.src = "<?php echo $c->getFileUrlFromTheme('images/online-on.gif'); ?>";
      btn.alt = this.i18n.hideonline;
      btn.title = btn.alt;
      content.style.display = 'block';
    }
    this.refresh_Chat();
    this.refresh_OnlineAndSmileys();
  },
  
  /**
   * Resize online and smileys
   */
  refresh_OnlineAndSmileys: function()
  {
    var onlinediv = $('<?php echo $prefix; ?>online');
    var smileysdiv = $('<?php echo $prefix; ?>smileys');
    
    if (this.showWhosOnline)
    {
      smileysdiv.style.height='100%';
    }
    else
    {
      smileysdiv.style.height= '';
    }
    
    if (this.showSmileys)
    {
      onlinediv.style.height='100%';
    }
    else
    {
      onlinediv.style.height= '';
    }
  },
  
  /**
   * Resize chat
   */
  refresh_Chat: function()
  {
    var chatdiv = $('<?php echo $prefix; ?>chat');
    var wordsdiv = $('<?php echo $prefix; ?>words');
    if (this.showWhosOnline && this.showSmileys)
    {
      chatdiv.style.width='100%';
    }
    else
    {
      chatdiv.style.width='';
    }
  }
  
};