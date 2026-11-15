'use strict';
// Exercise the actual restoration helper with detached-button and existing-button cases.
const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm'),path=require('node:path');
const code=fs.readFileSync(path.join(__dirname,'../plugins/flexicontent_fields/image/js/form.js'),'utf8');
const helper=code.match(/fcfield_image\.prepareMediaClone = function\(source, clone\)[\s\S]*?\n\t};/);
assert(helper,'Image media clone helper must be present');
const context={fcfield_image:{}};vm.runInNewContext(helper[0],context,{timeout:1000});
const prepare=context.fcfield_image.prepareMediaClone;
let copies=0;const clear={cloneNode(deep){assert.equal(deep,true);copies++;return {cloned:true};}};
const source={querySelectorAll(){return [{buttonClearEl:clear}];}};
function media(hasButton=false,hasGroup=true){
 const appended=[];const group={appendChild(node){appended.push(node);}};
 return {appended,getAttribute(){return '.button-clear';},querySelector(selector){if(selector==='.button-clear')return hasButton||appended.length?{}:null;if(selector==='.input-group')return hasGroup?group:null;throw Error('Unexpected selector '+selector);},appendChild:group.appendChild};
}
const target=media(),clone={querySelectorAll(){return [target];}};
prepare(source,clone);assert.equal(copies,1);assert.deepEqual(target.appended,[{cloned:true}]);
assert.equal(source.querySelectorAll()[0].buttonClearEl,clear,'Original detached button must remain owned by original control');
prepare(source,clone);assert.equal(copies,1,'Repeated preparation must not duplicate buttons');
const present=media(true);prepare(source,{querySelectorAll(){return [present];}});assert.equal(copies,1);
prepare({querySelectorAll(){return [];}},{querySelectorAll(){return [media()];}});assert.equal(copies,1);
prepare(source,{querySelectorAll(){return [];}});assert.equal(copies,1);
const fallback=media(false,false);prepare(source,{querySelectorAll(){return [fallback];}});assert.equal(fallback.appended.length,1);assert.equal(copies,2);
console.log('6 detached media-button restoration checks passed');
