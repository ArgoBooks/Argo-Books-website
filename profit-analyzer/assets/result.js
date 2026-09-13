/* Profit Analyzer result page — renders the live analytics payload.
   Data comes from window.PA_ANALYTICS (an upload, handed over via sessionStorage)
   or, when absent, the bundled sample. Charts/cards/tabs with no data are hidden,
   so a real upload shows only the dimensions its spreadsheet supports.
   ECharts is loaded separately. window.PA_ASSETS = base path for self-hosted assets. */
(function(){
  var ASSETS = window.PA_ASSETS || '';
  var TOOL = window.PA_TOOL || '';
  var PALETTE = ['#3b82f6','#10b981','#f59e0b','#ef4444','#8b5cf6','#06b6d4','#ec4899','#64748b','#14b8a6','#f97316'];
  var charts = {};
  // Initial theme comes from the attribute set by the head script (persisted choice).
  var dark = (document.documentElement.getAttribute('data-theme') === 'dark'), curTab = 'dashboard';
  var A = null; // the analytics payload
  var CUR = '$'; // currency symbol for this dataset (set in boot from meta)

  function TT(){ return dark
    ? {axis:'#8a97ab',split:'rgba(255,255,255,.08)',axisLine:'rgba(255,255,255,.13)',tipBg:'#111c33',tipBorder:'rgba(255,255,255,.12)',tipText:'#e2e8f0',sankN:'#cbd5e1',sankV:'#8a97ab'}
    : {axis:'#94a3b8',split:'#f1f5f9',axisLine:'#e2e8f0',tipBg:'#ffffff',tipBorder:'#e6ebf2',tipText:'#0f172a',sankN:'#475569',sankV:'#9aa6b6'}; }
  function tip(extra){ var t=TT(); return Object.assign({backgroundColor:t.tipBg,borderColor:t.tipBorder,borderWidth:1,textStyle:{color:t.tipText,fontFamily:'Hanken Grotesk',fontSize:12.5}}, extra||{}); }
  function init(id, opt){ var d=document.getElementById(id); if(!d||charts[id])return; var c=echarts.init(d,null,{renderer:'svg'}); c.setOption(opt); charts[id]=c; }
  function base(o){ o.backgroundColor='transparent'; o.textStyle={fontFamily:'Hanken Grotesk'}; return o; }
  function axisX(cats){ var t=TT(); return {type:'category',data:cats,boundaryGap:true,axisLine:{lineStyle:{color:t.axisLine}},axisTick:{show:false},axisLabel:{color:t.axis,fontSize:11}}; }
  function axisY(){ var t=TT(); return {type:'value',splitLine:{lineStyle:{color:t.split}},axisLine:{show:false},axisLabel:{color:t.axis,fontSize:11}}; }
  function fade(hex){ return new echarts.graphic.LinearGradient(0,0,0,1,[{offset:0,color:hex+'33'},{offset:1,color:hex+'00'}]); }
  function esc(s){ var d=document.createElement('div'); d.textContent=(s==null?'':String(s)); return d.innerHTML; }

  function line(cats, series){
    return base({ tooltip:tip({trigger:'axis'}), legend: series.length>1?{top:0,icon:'roundRect',itemWidth:11,itemHeight:11,textStyle:{color:TT().axis,fontSize:12}}:undefined,
      grid:{left:52,right:18,top:series.length>1?34:14,bottom:28}, xAxis:axisX(cats), yAxis:axisY(),
      series:series.map(function(s){ return {name:s.name,type:'line',smooth:true,showSymbol:false,data:s.data,lineStyle:{width:3,color:s.color},itemStyle:{color:s.color},areaStyle:s.area?{color:fade(s.color)}:null}; }) });
  }
  function bars(cats, series, opt){
    opt=opt||{};
    return base({ tooltip:tip({trigger:'axis',axisPointer:{type:'shadow'}}), legend: series.length>1?{top:0,icon:'roundRect',itemWidth:11,itemHeight:11,textStyle:{color:TT().axis,fontSize:12}}:undefined,
      grid:{left:52,right:18,top:series.length>1?34:14,bottom:28}, xAxis:axisX(cats), yAxis:axisY(),
      series:series.map(function(s){ return {name:s.name,type:'bar',stack:opt.stack?'t':null,data:s.data,barMaxWidth:opt.thin?16:26,itemStyle:{color:s.color,borderRadius:opt.stack?0:[4,4,0,0]}}; }) });
  }
  function pie(data, money){
    return base({ tooltip:tip({trigger:'item',formatter: money?('{b}: '+CUR+'{c} ({d}%)'):'{b}: {c} ({d}%)'}),
      legend:{type:'scroll',orient:'vertical',right:6,top:'middle',icon:'circle',itemWidth:10,itemHeight:10,textStyle:{color:TT().axis,fontSize:12}},
      series:[{type:'pie',radius:['46%','72%'],center:['36%','52%'],avoidLabelOverlap:true,label:{show:false},
        itemStyle:{borderColor:'#fff',borderWidth:2,borderRadius:4},
        data:data.map(function(d,i){ return {name:d.name,value:d.value,itemStyle:{color:PALETTE[i%PALETTE.length]}}; })}] });
  }

  // hide the card wrapping a chart id (when there's no data for it)
  function hideCard(id){ var d=document.getElementById(id); if(d){ var c=d.closest('.chartcard,.listcard'); if(c)c.style.display='none'; } }
  function chart(id, opt){ if(opt){ init(id,opt); } else { hideCard(id); } }
  // pie helper that hides the card when the dataset is empty
  function pieOr(id, data, money){ chart(id, (data&&data.length)? pie(data,money):null); }
  function has(arr){ return arr && arr.length; }

  // ---------- Sankey (dashboard money-flow) ----------
  function sankeyColor(name){
    var n=name.toLowerCase();
    if(n==='revenue') return '#6f8fb3';
    if(n==='total costs'||n==='total spent') return '#8aa0b8'; // neutral funding node
    if(n==='profit') return '#1f9d6b';
    if(n==='loss'||n==='shortfall') return '#c9544f'; // the red shortfall stream
    if(/cost|ads|advert|fee|other|shipping|expense/.test(n)) return '#d76b66'; // leaks (red)
    return '#6fae93'; // surviving stages (green)
  }
  function sankey(flow){
    var el=document.getElementById('sankeyChart'); if(!el||charts.sankey||!flow)return;
    var c=echarts.init(el,null,{renderer:'svg'}); charts.sankey=c;
    // Percentage base: revenue when present (profit view), else total costs
    // (loss view shows a cost breakdown, so % is share of total costs).
    var VAL=flow.nodes||{}; var REV=VAL['Revenue']||VAL['Total costs']||1;
    function f(n){return CUR+Number(n).toLocaleString('en-US');}
    var L=(flow.links||[]).map(function(a){return {source:a[0],target:a[1],value:a[2]};});
    var t=TT(), pn=dark?'#5cf0b4':'#0f766e', pv=dark?'#34d399':'#10a37f';
    var nodes=Object.keys(VAL).map(function(n){ var o={name:n,value:VAL[n],itemStyle:{color:sankeyColor(n),borderWidth:0,borderRadius:6}};
      if(n==='Revenue')o.label={position:'left'};
      if(n==='Profit')o.label={position:'right',rich:{n:{color:pn,fontSize:12.5,fontWeight:700,lineHeight:17},v:{color:pv,fontSize:12,fontWeight:700,lineHeight:15}}};
      return o; });
    c.setOption({backgroundColor:'transparent',animationDuration:1100,
      tooltip:tip({trigger:'item',confine:true,padding:[9,13],extraCssText:'box-shadow:0 12px 30px -10px rgba(16,24,40,.22);border-radius:10px',
        formatter:function(p){ if(p.dataType==='node')return '<b>'+p.name+'</b><br><span style="opacity:.7">'+f(p.value)+' · '+Math.round(p.value/REV*100)+'%</span>'; return '<span style="opacity:.7">'+p.data.source+' → '+p.data.target+'</span><br><b>'+f(p.value)+'</b>'; }}),
      series:[{type:'sankey',left:92,right:116,top:12,bottom:24,nodeWidth:12,nodeGap:18,nodeAlign:'left',draggable:false,
        emphasis:{focus:'adjacency',lineStyle:{opacity:.5}},lineStyle:{color:'gradient',curveness:.52,opacity:.3},
        label:{fontFamily:'Hanken Grotesk',position:'right',formatter:function(p){return '{n|'+p.name+'}\n{v|'+f(p.value)+'}';},rich:{n:{fontSize:12.5,fontWeight:600,color:t.sankN,lineHeight:17},v:{fontSize:11.5,fontWeight:500,color:t.sankV,lineHeight:15}}},
        data:nodes,links:L}]});
  }

  // ---------- per-tab chart builders (read from A) ----------
  var builders = {
    dashboard:function(){
      var d=A.dashboard||{};
      sankey(d.flow);
      chart('c_profitTrend', d.profitTrend? line(d.profitTrend.cats,[{name:'Profit',data:d.profitTrend.data,color:'#10b981',area:true}]):null);
      var sve=d.salesVsExp;
      chart('c_salesVsExp', sve? bars(sve.revenue.cats,[{name:'Sales',data:sve.revenue.data,color:'#3b82f6'},{name:'Expenses',data:sve.expenses.data,color:'#ef4444'}]):null);
      chart('c_salesTrend', (sve&&sve.revenue)? line(sve.revenue.cats,[{name:'Revenue',data:sve.revenue.data,color:'#3b82f6',area:true}]):null);
      pieOr('c_revDist', d.revByProduct, true);
      chart('c_purchTrend', (sve&&sve.expenses)? line(sve.expenses.cats,[{name:'Expenses',data:sve.expenses.data,color:'#ef4444',area:true}]):null);
      pieOr('c_expDist', d.expDist, true);
    },
    products:function(){ renderCards('products', (A.products&&A.products.cards)||[]); },
    geographic:function(){
      var g=A.geographic||{};
      pieOr('c_cOrigin', g.origin);
      hideCard('c_compOrigin');
      pieOr('c_cDest', g.destination);
      hideCard('c_compDest');
      if(has(g.map)) geoMap(g.map); else hideCard('c_geoMap');
    },
    customers:function(){ renderCards('customers', (A.customers&&A.customers.cards)||[]); },
    taxes:function(){
      var t=A.taxes||{};
      var cvp=t.collectedVsPaid;
      chart('c_taxVsPaid', cvp? bars(cvp.collected.cats,[{name:'Collected',data:cvp.collected.data,color:'#10b981'},{name:'Paid',data:cvp.paid.data,color:'#ef4444'}]):null);
      pieOr('c_taxCat', t.byCategory);
      ['c_taxRate','c_taxLiab','c_taxProd','c_expRevTax'].forEach(hideCard);
    }
  };

  // ---------- world map ----------
  var mapReady=false, mapPending=false;
  function geoMap(mapData){
    var el=document.getElementById('c_geoMap'); if(!el||charts.geoMap)return;
    var maxv=mapData.reduce(function(m,d){return Math.max(m,d.value);},0)||1;
    function draw(){
      var c=echarts.init(el,null,{renderer:'svg'}); charts.geoMap=c; var t=TT();
      c.setOption(base({ tooltip:tip({trigger:'item',formatter:function(p){return p.name+(p.value?': '+CUR+Number(p.value).toLocaleString():': —');}}),
        visualMap:{left:14,bottom:14,min:0,max:maxv,calculable:true,inRange:{color:dark?['#1e3356','#2f6fd0','#7db4ff']:['#dbeafe','#60a5fa','#1e40af']},text:['High','Low'],textStyle:{color:t.axis}},
        series:[{type:'map',map:'world',roam:false,itemStyle:{areaColor:dark?'#16223c':'#eef2f7',borderColor:dark?'rgba(255,255,255,.10)':'#dbe2ec'},emphasis:{itemStyle:{areaColor:'#10b981'},label:{show:false}},
          data:mapData}] }));
    }
    if(mapReady){ draw(); return; }
    if(mapPending)return; mapPending=true;
    fetch(ASSETS + 'world.json').then(function(r){return r.json();})
      .then(function(geo){ echarts.registerMap('world',geo); mapReady=true; draw(); })
      .catch(function(){ el.innerHTML='<div style="padding:30px;text-align:center;color:#94a3b8;font-size:13px">World map could not load.</div>'; });
  }

  // ---------- pagination (matches the Argo Books app's table footer) ----------
  var CHEV_L='<svg viewBox="0 0 24 24" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>';
  var CHEV_R='<svg viewBox="0 0 24 24" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>';
  function pageWindow(cur, total){ // sliding window of up to 5 numbers
    var start=Math.max(1, cur-2), end=Math.min(total, start+4); start=Math.max(1, end-4);
    var a=[]; for(var i=start;i<=end;i++)a.push(i); return a;
  }
  function pagerText(total, page, size, totalPages, noun){
    if(total===0) return '0 '+noun.plural;
    if(totalPages<=1) return total===1 ? ('1 '+noun.singular) : (total+' '+noun.plural);
    var s=(page-1)*size+1, e=Math.min(page*size, total);
    return s+'-'+e+' of '+total+' '+noun.plural;
  }
  // rows: full array; foot: footer element; renderRows(pageRows): fills the body.
  function buildPager(rows, foot, renderRows, noun){
    var state={size:10, page:1};
    function totalPages(){ return Math.max(1, Math.ceil(rows.length/state.size)); }
    function draw(){
      var tp=totalPages(); if(state.page>tp) state.page=tp;
      var start=(state.page-1)*state.size;
      renderRows(rows.slice(start, start+state.size));
      var right='';
      if(tp>1){
        var nums=pageWindow(state.page,tp).map(function(p){ return '<button class="pgbtn'+(p===state.page?' active':'')+'" data-p="'+p+'">'+p+'</button>'; }).join('');
        right='<div class="pgpages"><button class="pgbtn" data-act="prev"'+(state.page===1?' disabled':'')+'>'+CHEV_L+'</button>'+nums
          +'<button class="pgbtn" data-act="next"'+(state.page===tp?' disabled':'')+'>'+CHEV_R+'</button></div>';
      }
      foot.innerHTML='<div class="pgleft"><span>'+esc(pagerText(rows.length,state.page,state.size,tp,noun))+'</span>'
        +'<span class="pgsep">|</span><span>Rows per page:</span>'
        +'<select class="pgsize">'+[10,25,50,100].map(function(s){ return '<option'+(s===state.size?' selected':'')+'>'+s+'</option>'; }).join('')+'</select></div>'+right;
      foot.querySelector('.pgsize').onchange=function(){ state.size=parseInt(this.value,10); state.page=1; draw(); };
      foot.querySelectorAll('.pgpages .pgbtn').forEach(function(b){ b.onclick=function(){
        var act=b.dataset.act;
        if(act==='prev'){ if(state.page>1) state.page--; }
        else if(act==='next'){ if(state.page<totalPages()) state.page++; }
        else { state.page=parseInt(b.dataset.p,10); }
        draw();
      }; });
    }
    draw();
  }
  function nounFor(label){ var p=String(label||'rows').toLowerCase(); return {plural:p, singular:p.replace(/s$/,'')}; }

  // ---------- generic card renderer (products / customers tabs) ----------
  function renderCards(panelName, cards){
    var grid=document.querySelector('.panel[data-panel="'+panelName+'"] .cgrid'); if(!grid)return;
    grid.innerHTML='';
    cards.forEach(function(card, idx){
      var div=document.createElement('div');
      div.className='chartcard'+(card.span2?' span2':'');
      var html='<div class="ttl">'+esc(card.title)+'</div>'+(card.meta?'<div class="cmeta">'+esc(card.meta)+'</div>':'');
      if(card.type==='table'){
        var bid='cb_'+panelName+'_'+idx, fid='cf_'+panelName+'_'+idx;
        html+='<div style="overflow:auto"><table class="dtable"><thead><tr>'
          + card.columns.map(function(c){ return '<th>'+esc(c)+'</th>'; }).join('')
          + '</tr></thead><tbody id="'+bid+'"></tbody></table></div>'
          + '<div class="pgfoot" id="'+fid+'"></div>';
        div.innerHTML=html; grid.appendChild(div);
        var bodyEl=document.getElementById(bid);
        var noun=card.noun ? {plural:card.noun+'s', singular:card.noun} : nounFor('rows');
        buildPager(card.rows, document.getElementById(fid), function(pageRows){
          bodyEl.innerHTML=pageRows.map(function(r){ return '<tr>'+r.map(function(v){ return '<td>'+esc(v)+'</td>'; }).join('')+'</tr>'; }).join('');
        }, noun);
      } else {
        var id='cc_'+panelName+'_'+idx;
        html+='<div class="ec" id="'+id+'"></div>';
        div.innerHTML=html; grid.appendChild(div);
        if(card.type==='pie'){ init(id, pie(card.data, card.money)); }
        else if(card.type==='bars'){ init(id, bars(card.cats, card.series)); }
      }
    });
  }

  // ---------- KPI + table + meta rendering ----------
  function renderKpis(panelName, kpis){
    var panel=document.querySelector('.panel[data-panel="'+panelName+'"]'); if(!panel)return;
    var box=panel.querySelector('.kpis'); if(!box)return;
    if(!has(kpis)){ box.style.display='none'; return; }
    box.innerHTML=kpis.map(function(k){
      return '<div class="kpi '+(k.cls||'')+'"><div class="lbl">'+esc(k.lbl)+'</div><div class="val">'+esc(k.val)+'</div>'
        + (k.sub?'<div class="sub '+(k.subcls||'')+'">'+esc(k.sub)+'</div>':'') + '</div>';
    }).join('');
  }
  function renderFlowStat(){
    var d=A.dashboard; if(!d||!d.flow)return;
    var kept=d.flow.kept||0;
    var stat=document.querySelector('.keptstat b'); if(stat)stat.textContent=kept+'%';
    var ks=document.querySelector('.keptstat');
    if(ks){
      var neg=kept<0;
      ks.classList.toggle('neg', neg);
      var span=ks.querySelector('span'); if(span) span.textContent = neg ? 'lost to costs' : 'kept as profit';
    }
  }
  function renderMeta(){
    var m=A.meta||{};
    var fileEl=document.querySelector('.rhead .file'); if(fileEl){
      var badge=fileEl.querySelector('.badge'); var name=esc(m.filename||'your-spreadsheet.xlsx');
      fileEl.childNodes.forEach&&fileEl.childNodes.forEach(function(n){ if(n.nodeType===3)n.textContent=' '+m.filename+' '; });
      if(badge)badge.textContent=Number(m.rows||0).toLocaleString()+' rows analyzed';
    }
  }
  // Multi-entity cleaned data: one tab per entity present (Sales, Expenses,
  // Customers, Products, …), matching the downloaded spreadsheet exactly.
  function renderCleaned(){
    var sheets=A.cleanedSheets||[];
    var tabsEl=document.getElementById('cleanTabs');
    var head=document.getElementById('cleanHead');
    var body=document.getElementById('cleanBody');
    var foot=document.getElementById('cleanFoot');
    var wrap=document.querySelector('.cleanwrap');
    if(!tabsEl||!head||!body){ return; }
    if(!sheets.length){ if(wrap)wrap.style.display='none'; var st=document.querySelector('.sectitle'); if(st)st.style.display='none'; return; }
    tabsEl.innerHTML=sheets.map(function(s,i){ return '<span class="ctab'+(i===0?' active':'')+'" data-i="'+i+'">'+esc(s.label)+'</span>'; }).join('');
    function renderSheet(i){
      var s=sheets[i];
      head.innerHTML='<tr>'+s.columns.map(function(c,j){ return '<th'+(s.aligns[j]==='right'?' style="text-align:right"':'')+'>'+esc(c)+'</th>'; }).join('')+'</tr>';
      buildPager(s.rows, foot, function(pageRows){
        body.innerHTML=pageRows.map(function(r){ return '<tr>'+r.map(function(v,j){ return '<td'+(s.aligns[j]==='right'?' class="num"':'')+'>'+esc(v)+'</td>'; }).join('')+'</tr>'; }).join('');
      }, nounFor(s.label));
    }
    tabsEl.querySelectorAll('.ctab').forEach(function(t){ t.onclick=function(){ tabsEl.querySelectorAll('.ctab').forEach(function(x){x.classList.remove('active');}); t.classList.add('active'); renderSheet(+t.dataset.i); }; });
    renderSheet(0);
  }

  // ---------- tabs ----------
  function show(name){
    curTab=name;
    document.querySelectorAll('.tabbtn').forEach(function(b){ b.classList.toggle('active', b.dataset.tab===name); });
    document.querySelectorAll('.panel').forEach(function(p){ p.classList.toggle('active', p.dataset.panel===name); });
    if(builders[name] && !built[name]){ built[name]=true; builders[name](); }
    Object.keys(charts).forEach(function(k){ charts[k].resize(); });
  }
  var built={};

  function hideAbsentTabs(){
    var present={}; (A.tabs||[]).forEach(function(t){ present[t]=true; });
    document.querySelectorAll('.tabbtn').forEach(function(b){ if(!present[b.dataset.tab]) b.style.display='none'; });
    document.querySelectorAll('.panel').forEach(function(p){ if(!present[p.dataset.panel]) p.style.display='none'; });
  }

  // ---------- download + email (post the client-held normalized data) ----------
  function wireActions(){
    var norm=window.PA_NORMALIZED || null;
    function postBlob(url){
      return fetch(url,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({normalized:norm})});
    }
    document.querySelectorAll('.btn-download, .cleanbar .dl').forEach(function(btn){
      btn.addEventListener('click', function(){
        if(!norm){ return; }
        btn.classList.add('busy');
        postBlob(TOOL+'download.php').then(function(r){ return r.blob(); }).then(function(blob){
          var url=URL.createObjectURL(blob); var a=document.createElement('a');
          a.href=url; a.download='cleaned-'+(new Date().toISOString().slice(0,10))+'.xlsx';
          document.body.appendChild(a); a.click(); a.remove(); URL.revokeObjectURL(url);
        }).catch(function(){}).then(function(){ btn.classList.remove('busy'); });
      });
    });
    wireEmailModal(norm);
  }

  // ---------- email modal (confirm address + opt-in, then send) ----------
  function wireEmailModal(norm){
    var form=document.querySelector('.email form');
    var modal=document.getElementById('emailModal');
    if(!form||!modal)return;
    var emEmail=document.getElementById('emEmail');
    var emSub=document.getElementById('emSubscribe');
    var emSend=document.getElementById('emSend');
    var emMsg=document.getElementById('emMsg');

    function open(){
      var pre=(form.querySelector('input[type=email]').value||'').trim();
      emEmail.value=pre;
      emMsg.textContent=''; emMsg.className='pa-modal-msg';
      emSub.checked=true;
      emSend.disabled=false; emSend.textContent='Send';
      modal.hidden=false;
      setTimeout(function(){ emEmail.focus(); },0);
    }
    function close(){ modal.hidden=true; }

    // The page's email row no longer sends directly — it opens the modal so the
    // user can confirm their address and choose the opt-in before anything sends.
    form.addEventListener('submit', function(e){ e.preventDefault(); open(); });
    modal.querySelectorAll('[data-close]').forEach(function(el){ el.addEventListener('click', close); });
    document.addEventListener('keydown', function(e){ if(e.key==='Escape' && !modal.hidden) close(); });

    emSend.addEventListener('click', function(){
      var email=(emEmail.value||'').trim();
      if(!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)){
        emMsg.textContent='Please enter a valid email address.'; emMsg.className='pa-modal-msg err'; return;
      }
      emSend.disabled=true; emSend.textContent='Sending…'; emMsg.textContent=''; emMsg.className='pa-modal-msg';
      fetch(TOOL+'email.php',{method:'POST',headers:{'Content-Type':'application/json'},
        body:JSON.stringify({email:email,normalized:norm,subscribe:emSub.checked})})
        .then(function(r){return r.json();})
        .then(function(j){
          if(j&&j.ok){
            emMsg.textContent=(j.message||'Sent. Check your inbox.'); emMsg.className='pa-modal-msg ok';
            emSend.textContent='Sent ✓';
            setTimeout(close, 2600);
          } else {
            emMsg.textContent=(j&&j.message)||'Could not send. Please try again.'; emMsg.className='pa-modal-msg err';
            emSend.disabled=false; emSend.textContent='Send';
          }
        })
        .catch(function(){ emMsg.textContent='Could not send. Please try again.'; emMsg.className='pa-modal-msg err';
          emSend.disabled=false; emSend.textContent='Send'; });
    });
  }

  // ---------- dark toggle ----------
  function wireTheme(){
    var tbtn=document.getElementById('themeToggle'); if(!tbtn)return;
    tbtn.setAttribute('aria-pressed', dark); // reflect the restored theme
    tbtn.addEventListener('click', function(){
      dark=!dark;
      document.documentElement.setAttribute('data-theme', dark?'dark':'light');
      tbtn.setAttribute('aria-pressed', dark);
      try{ localStorage.setItem('pa_theme', dark?'dark':'light'); }catch(e){} // persist across uploads
      Object.keys(charts).forEach(function(k){ charts[k].dispose(); delete charts[k]; });
      Object.keys(built).forEach(function(k){ delete built[k]; });
      mapReady=mapReady; // keep registered map
      built[curTab]=true; builders[curTab] && builders[curTab]();
      Object.keys(charts).forEach(function(k){ charts[k].resize(); });
    });
  }

  // ---------- boot ----------
  function showEmptyState(){
    var tabbar=document.querySelector('.tabbar'); if(tabbar)tabbar.style.display='none';
    document.querySelectorAll('.panel').forEach(function(p){ p.style.display='none'; });
    var main=document.getElementById('paMain'); if(!main)return;
    main.insertAdjacentHTML('afterbegin',
      '<div class="empty-note" style="text-align:center;max-width:560px;margin:10px auto 26px;padding:26px;border:1px solid var(--line,#e6ebf2);border-radius:16px;background:#fff">'
      +'<h3 style="margin:0 0 8px;font-family:Fraunces,Georgia,serif">We cleaned your file</h3>'
      +'<p style="color:#64748b;margin:0">It didn\'t contain enough sales or expense transactions to chart, but your cleaned, organized data is ready below.</p></div>');
  }

  function render(){
    renderMeta();
    hideAbsentTabs();
    if(!has(A.tabs)){ showEmptyState(); renderCleaned(); wireActions(); return; }
    ['dashboard','products','customers','taxes'].forEach(function(name){ if(A[name]) renderKpis(name, A[name].kpis); });
    renderFlowStat();
    renderCleaned();
    document.getElementById('tabbar').addEventListener('click', function(e){ var b=e.target.closest('.tabbtn'); if(b && b.style.display!=='none') show(b.dataset.tab); });
    var first=(A.tabs&&A.tabs[0])||'dashboard';
    show(first);
    wireTheme();
    wireActions();
    window.addEventListener('resize', function(){ Object.keys(charts).forEach(function(k){ charts[k].resize(); }); });
  }

  function boot(data){ A=data||{}; if(!A.tabs)A.tabs=[]; CUR=(A.meta&&A.meta.currencySymbol)||'$'; render(); }

  if(window.PA_ANALYTICS){ boot(window.PA_ANALYTICS); }
  else {
    fetch(ASSETS+'sample-analytics.json').then(function(r){return r.json();})
      .then(function(d){ if(!window.PA_NORMALIZED){ // also load sample normalized so download/email work in the demo
          return fetch(ASSETS+'sample-normalized.json').then(function(r){return r.json();}).then(function(n){ window.PA_NORMALIZED=n; boot(d); }).catch(function(){ boot(d); });
        } boot(d); })
      .catch(function(){ document.querySelector('.wrap').insertAdjacentHTML('afterbegin','<p style="padding:20px;color:#ef4444">Could not load results. Please try uploading again.</p>'); });
  }
})();
