/* Owner landing page — sample money-flow Sankey + diverging product chart.
   Animations fire on scroll-into-view. ECharts is loaded separately. */
(function(){
  var el = document.getElementById('sankeyChart');
  if(!el || !window.echarts) return;
  var chart = echarts.init(el, null, {renderer:'svg'});

  var REV = 24800;
  function fmt(n){ return '$' + n.toLocaleString('en-US'); }

  var VAL = {'Revenue':24800,'Cost of goods':13400,'Gross profit':11400,'Ads & marketing':4460,'After ads':6940,'Fees':2230,'Profit':4710};
  var LINKS = [
    {source:'Revenue',      target:'Cost of goods',   value:13400},
    {source:'Revenue',      target:'Gross profit',    value:11400},
    {source:'Gross profit', target:'Ads & marketing', value:4460},
    {source:'Gross profit', target:'After ads',       value:6940},
    {source:'After ads',    target:'Fees',            value:2230},
    {source:'After ads',    target:'Profit',          value:4710}
  ];

  // The surviving stream cools from steel-blue into emerald as it makes it through; every cost peels off in muted red so the leaks read as money lost and the profit stays the one green.
  var C = {
    'Revenue':         '#6f8fb3',
    'Cost of goods':   '#d76b66',
    'Gross profit':    '#6fae93',
    'Ads & marketing': '#e3938c',
    'After ads':       '#46a37e',
    'Fees':            '#c9544f',
    'Profit':          '#1f9d6b'
  };

  var nodes = Object.keys(VAL).map(function(name){
    var node = { name:name, value:VAL[name], itemStyle:{ color:C[name], borderWidth:0, borderRadius:6 } };
    if(name==='Revenue'){ node.label = { position:'left' }; }
    if(name==='Profit'){
      node.label = { position:'right', rich:{
        n:{ color:'#0f766e', fontSize:12.5, fontWeight:700, lineHeight:17 },
        v:{ color:'#10a37f', fontSize:12, fontWeight:700, lineHeight:15 }
      }};
    }
    return node;
  });

  var sankeyOption = {
    backgroundColor:'transparent', animationDuration:1200, animationEasing:'cubicOut',
    tooltip:{
      trigger:'item', triggerOn:'mousemove', confine:true,
      backgroundColor:'#ffffff', borderColor:'#e6ebf2', borderWidth:1, padding:[9,13],
      extraCssText:'box-shadow:0 12px 30px -10px rgba(16,24,40,.22);border-radius:10px',
      textStyle:{ color:'#0f172a', fontFamily:'Hanken Grotesk', fontSize:12.5 },
      formatter:function(p){
        if(p.dataType==='node'){
          return '<b style="font-weight:700">'+p.name+'</b><br><span style="color:#64748b">'+fmt(p.value)+' &middot; '+Math.round(p.value/REV*100)+'% of revenue</span>';
        }
        return '<span style="color:#64748b">'+p.data.source+' &rarr; '+p.data.target+'</span><br><b style="font-weight:700">'+fmt(p.value)+'</b>';
      }
    },
    series:[{
      type:'sankey', left:96, right:118, top:14, bottom:28,
      nodeWidth:12, nodeGap:18, nodeAlign:'left', draggable:false,
      emphasis:{ focus:'adjacency', lineStyle:{ opacity:0.5 } },
      lineStyle:{ color:'gradient', curveness:0.52, opacity:0.3 },
      itemStyle:{ borderWidth:0 },
      label:{
        fontFamily:'Hanken Grotesk', position:'right',
        formatter:function(p){ return '{n|'+p.name+'}\n{v|'+fmt(p.value)+'}'; },
        rich:{
          n:{ fontSize:12.5, fontWeight:600, color:'#475569', lineHeight:17 },
          v:{ fontSize:11.5, fontWeight:500, color:'#9aa6b6', lineHeight:15 }
        }
      },
      data:nodes, links:LINKS
    }]
  };

  // Fire each chart's animation only when it scrolls into view, not on load.
  function onView(elm, cb){
    if(!elm) return;
    if('IntersectionObserver' in window){
      var io = new IntersectionObserver(function(entries){
        entries.forEach(function(e){ if(e.isIntersecting){ cb(); io.disconnect(); } });
      }, {threshold:0.25});
      io.observe(elm);
    } else { cb(); }
  }

  var drawn = false;
  onView(el, function(){ if(!drawn){ drawn = true; chart.setOption(sankeyOption); } });

  var revbars = document.querySelector('.revbars');
  onView(revbars, function(){ revbars.classList.add('in-view'); });

  window.addEventListener('resize', function(){ chart.resize(); });
})();
