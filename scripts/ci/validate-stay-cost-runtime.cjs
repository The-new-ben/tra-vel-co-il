const test=require('node:test'),assert=require('node:assert/strict');
const {parseMoney,calculateComparison,LIMITS}=require('../../theme/tra-vel-v2/assets/js/stay-cost-engine.js');
const hotel=(name='מלון שבחרתי',amount='100',basis='room',period='night')=>({name,quote:{amount,basis,period},extras:{breakfast:{state:'not_planned'},transport:{state:'not_planned'},fees:{state:'unknown'}}});
const state=(first=hotel(),second=hotel('מלון נוסף'))=>({currency:'EUR',nights:'3',guests:'2',rooms:'1',hotels:[first,second]});
const result=s=>{const c=calculateComparison(s);assert.equal(c.valid,true,JSON.stringify(c.errors));return c.results[0];};

test('decimal money is exact, locale-friendly and never treats blank as zero',()=>{
 for(const [raw,minor] of [['0',0],['0.00',0],['0.1',10],['0.10',10],['12,34',1234],['1000000.00',100000000],[' 1.25 ',125]])assert.deepEqual(parseMoney(raw),{valid:true,minor});
 for(const raw of ['', ' ', '-1','-0','1.234','1,000','1e3','Infinity','NaN','€12','1 000','12.','+12','0x10','1000000.01','99999999999999999999999'])assert.equal(parseMoney(raw).valid,false,raw);
});
test('room nightly and whole-stay quotes use rooms but not guest count',()=>{
 const s=state();s.rooms='2';s.guests='5';assert.equal(result(s).knownTotalMinor,60000);
 s.hotels[0].quote.period='stay';assert.equal(result(s).knownTotalMinor,20000);
 assert.equal(result(s).averagePerGuestMinor,4000);
});
test('per-person quotes use guests and their explicit period',()=>{
 const s=state(hotel('לפי אדם','25.10','person','night'));s.guests='3';s.rooms='2';assert.equal(result(s).knownTotalMinor,22590);
 s.hotels[0].quote.period='stay';assert.equal(result(s).knownTotalMinor,7530);
});
test('daily breakfast, one-off party transport and one-off room taxes have distinct factors',()=>{
 const s=state();s.rooms='2';s.guests='3';s.hotels[0].extras={breakfast:{state:'extra',amount:'12.50',basis:'person',period:'day',days:'3'},transport:{state:'extra',amount:'40',basis:'party',period:'stay',days:'garbage'},fees:{state:'extra',amount:'10',basis:'room',period:'stay'}};
 const r=result(s);assert.equal(r.lodgingMinor,60000);assert.equal(r.knownTotalMinor,77250);assert.deepEqual(r.lines.map(l=>l.minor),[60000,11250,4000,2000]);assert.equal(r.complete,true);
});
test('daily charges use the entered day count, not an invented nights-plus-one',()=>{
 const s=state();s.hotels[0].extras.transport={state:'extra',amount:'5',basis:'person',period:'day',days:'4'};assert.equal(result(s).knownTotalMinor,34000);
});
test('unknown fees remain null and excluded from known total; zero is separately known',()=>{
 const s=state();const r=result(s);assert.equal(r.knownTotalMinor,30000);assert.deepEqual(r.unknown,['מסים ועמלות']);assert.equal(r.lines[3].minor,null);assert.equal(r.complete,false);
 s.hotels[0].extras.fees={state:'extra',amount:'0',basis:'party',period:'stay'};assert.equal(result(s).lines[3].minor,0);assert.equal(result(s).complete,true);
});
test('included costs are not charged twice and hidden stale amounts are ignored',()=>{
 const s=state();s.hotels[0].extras.breakfast={state:'included',amount:'-999999',basis:'wrong',period:'wrong'};const r=result(s);assert.equal(r.knownTotalMinor,30000);assert.deepEqual(r.included,['ארוחת בוקר']);assert.equal(r.lines[1].minor,0);
});
test('missing requested extras are errors rather than free items',()=>{
 const s=state();s.hotels[0].extras.fees={state:'extra',amount:'',basis:'party',period:'stay'};const c=calculateComparison(s);assert.equal(c.valid,false);assert.equal(c.results.length,0);assert.ok(c.errors.some(e=>e.field==='hotels.0.extras.fees.amount'));
});
test('zero lodging stays zero without inventing a hotel recommendation',()=>{
 const r=result(state(hotel('קיבלתי זיכוי','0')));assert.equal(r.knownTotalMinor,0);assert.equal(r.complete,false);
});
test('decimal accumulation never has floating-point cents drift',()=>{
 const s=state(hotel('עשיריות','0.10','person','night'));s.guests='3';s.hotels[0].extras.fees={state:'extra',amount:'0.20',basis:'party',period:'stay'};assert.equal(result(s).knownTotalMinor,110);
});
test('invalid currency and quantities fail; decimals/exponents/zero/negative/extremes are rejected',()=>{
 for(const field of ['nights','guests','rooms'])for(const invalid of ['', '0','-1','1.5','1e2','Infinity',String(LIMITS[field]+1)]){const s=state();s[field]=invalid;assert.equal(calculateComparison(s).valid,false,field+':'+invalid);}
 for(const currency of ['', 'eur','ZZZ','EUR/HUF']){const s=state();s.currency=currency;assert.equal(calculateComparison(s).valid,false,currency);}
});
test('two and three hotels supported; one/four, missing or overlong names fail',()=>{
 const s=state();s.hotels.push(hotel('שלישי'));assert.equal(calculateComparison(s).results.length,3);
 for(const count of [0,1,4]){const v=state();v.hotels=Array.from({length:count},()=>hotel());assert.equal(calculateComparison(v).valid,false);}
 for(const name of ['', '  ','x'.repeat(81),'bad\nname'])assert.equal(calculateComparison(state(hotel(name))).valid,false);
});
test('repeated edits recompute and never mutate caller data',()=>{
 const s=state();const before=JSON.stringify(s);assert.equal(result(s).knownTotalMinor,30000);assert.equal(JSON.stringify(s),before);s.nights='5';assert.equal(result(s).knownTotalMinor,50000);s.hotels[0].quote.amount='20.25';assert.equal(result(s).knownTotalMinor,10125);s.hotels[0].quote.amount='-2';assert.equal(calculateComparison(s).results.length,0);
});
test('currency selection does not apply a made-up exchange rate',()=>{
 const s=state();for(const currency of ['EUR','HUF','ILS','USD','GBP']){s.currency=currency;const c=calculateComparison(s);assert.equal(c.valid,true);assert.equal(c.currency,currency);assert.equal(c.results[0].knownTotalMinor,30000);}
});
test('average per guest is display-only and preserves the authoritative total',()=>{
 const s=state(hotel('חלוקה','1','room','stay'));s.guests='3';assert.equal(result(s).knownTotalMinor,100);assert.equal(result(s).averagePerGuestMinor,33);
});
test('malformed extra basis/period/day count/state fails, stale days on one-off costs do not',()=>{
 const extra={state:'extra',amount:'1',basis:'party',period:'day',days:'2'};
 for(const patch of [{basis:'anything'},{period:'anything'},{days:'0'},{days:'1.5'},{days:'367'},{state:'mystery'}]){const s=state();s.hotels[0].extras.fees={...extra,...patch};assert.equal(calculateComparison(s).valid,false,JSON.stringify(patch));}
 const s=state();s.hotels[0].extras.fees={...extra,period:'stay',days:'-900'};assert.equal(result(s).knownTotalMinor,30100);
});
test('safe maximums calculate exactly; oversized combined totals are rejected',()=>{
 const s=state(hotel('גבול','1000000','room','stay'));s.nights='365';s.rooms='20';s.guests='50';assert.equal(result(s).knownTotalMinor,2000000000);
 s.hotels[0].quote.period='night';s.hotels[0].quote.basis='person';const c=calculateComparison(s);assert.equal(c.valid,false);assert.ok(c.errors.some(e=>e.message.includes('גדול מדי')));
});
test('all quote bases and periods follow independent expected arithmetic over varied parties',()=>{
 for(const nights of [1,3,7,365])for(const guests of [1,2,5,50])for(const rooms of [1,2,20])for(const basis of ['room','person'])for(const period of ['night','stay']){const s=state(hotel('בדיקה','17.13',basis,period));Object.assign(s,{nights:String(nights),guests:String(guests),rooms:String(rooms)});assert.equal(result(s).knownTotalMinor,1713*(basis==='room'?rooms:guests)*(period==='night'?nights:1));}
});
