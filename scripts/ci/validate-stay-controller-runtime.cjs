'use strict';
const test=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),vm=require('node:vm');
const theme=path.resolve(__dirname,'../../theme/tra-vel-v2');
const controller=fs.readFileSync(path.join(theme,'assets/js/stay-shortlist.js'),'utf8');
const html=fs.readFileSync(path.join(theme,'inc/partials/stay-shortlist.html'),'utf8');
const templateHtml=html.match(/<template id="tr-stay-hotel-template">([\s\S]*?)<\/template>/)[1];
const dayKeys=[...templateHtml.matchAll(/data-field="([^"]+\.days)"/g)].map(m=>m[1].replace('__INDEX__','2'));
assert.equal(dayKeys.length,3,'Test all actual daily-extra fields in the bundled third-card template');

// A minimal event/DOM fixture executes the real controller; this is not browser or layout QA.
function harness(nights){
  const fields=new Map(),nodes=new Map();let hotels=2;
  const node=()=>({value:'',dataset:{},handlers:{},disabled:false,hidden:true,classList:{add(){},remove(){}},addEventListener(type,fn){this.handlers[type]=fn;},focus(){},matches(){return false;},querySelectorAll(){return[];}});
  fields.set('nights',Object.assign(node(),{value:nights,dataset:{field:'nights'}}));
  fields.set('hotels.2.name',node());
  for(let i=0;i<2;i++)for(const extra of ['breakfast','transport','fees'])fields.set(`hotels.${i}.extras.${extra}.days`,Object.assign(node(),{value:'3',dataset:{}}));
  const ids=['shortlist-form','hotel-inputs','results','result-grid','error-summary','comparison-status','add-hotel','calculate','reset-shortlist'];
  ids.forEach(id=>nodes.set('#tr-stay-'+id,node()));
  const inputs=nodes.get('#tr-stay-hotel-inputs');
  inputs.querySelectorAll=selector=>selector==='[data-hotel]'?Array.from({length:hotels},node):selector==='[data-field$=".days"]'?[...fields].filter(([k])=>k.endsWith('.days')).map(([,v])=>v):[];
  inputs.append=fragment=>{hotels++;fragment.days.forEach((field,i)=>fields.set(dayKeys[i],field));};
  const root={querySelector(selector){const m=selector.match(/^\[data-field="(.+)"\]$/);return m?fields.get(m[1]):nodes.get(selector);}};
  function template(){
    const days=dayKeys.map(()=>Object.assign(node(),{value:'3'}));
    return {innerHTML:'',content:{querySelectorAll:()=>days,cloneNode:()=>({days:days.map(d=>Object.assign(node(),{value:d.value}))})}};
  }
  const document={querySelector:selector=>selector==='[data-stay-shortlist]'?root:{innerHTML:templateHtml},createElement:tag=>{assert.equal(tag,'template');return template();}};
  vm.runInNewContext(controller,{document,window:{TravelStayCost:{}},Intl,setTimeout,clearTimeout});
  return {fields,add:()=>nodes.get('#tr-stay-add-hotel').handlers.click(),changeNights(value){fields.get('nights').value=value;nodes.get('#tr-stay-shortlist-form').handlers.input({target:fields.get('nights')});}};
}
for(const nights of ['1','7','365',''])test(`Third hotel inherits current nights ${JSON.stringify(nights)}`,()=>{const h=harness(nights);h.add();for(const key of dayKeys)assert.equal(h.fields.get(key).value,nights);});
test('Changing nights keeps manual day counts and updates automatic counts, including added card',()=>{
  const h=harness('7');h.add();const manual=h.fields.get(dayKeys[0]);manual.value='2';manual.dataset.manualDays='true';h.changeNights('9');assert.equal(manual.value,'2');for(const key of dayKeys.slice(1))assert.equal(h.fields.get(key).value,'9');assert.equal(h.fields.get('hotels.0.extras.breakfast.days').value,'9');
});
