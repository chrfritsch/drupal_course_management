!function(t){const e=t.en=t.en||{};e.dictionary=Object.assign(e.dictionary||{},{Bold:"Bold","Bold text":"Bold text",Code:"Code",Italic:"Italic","Italic text":"Italic text","Move out of an inline code style":"Move out of an inline code style",Strikethrough:"Strikethrough","Strikethrough text":"Strikethrough text",Subscript:"Subscript",Superscript:"Superscript",Underline:"Underline","Underline text":"Underline text"})}(window.CKEDITOR_TRANSLATIONS||(window.CKEDITOR_TRANSLATIONS={})),
/*!
 * @license Copyright (c) 2003-2024, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see LICENSE.md.
 */(()=>{var t={598:(t,e,i)=>{"use strict";i.d(e,{A:()=>r});var n=i(935),o=i.n(n)()((function(t){return t[1]}));o.push([t.id,".ck-content code{background-color:hsla(0,0%,78%,.3);border-radius:2px;padding:.15em}.ck.ck-editor__editable .ck-code_selected{background-color:hsla(0,0%,78%,.5)}",""]);const r=o},935:t=>{"use strict";t.exports=function(t){var e=[];return e.toString=function(){return this.map((function(e){var i=t(e);return e[2]?"@media ".concat(e[2]," {").concat(i,"}"):i})).join("")},e.i=function(t,i,n){"string"==typeof t&&(t=[[null,t,""]]);var o={};if(n)for(var r=0;r<this.length;r++){var s=this[r][0];null!=s&&(o[s]=!0)}for(var a=0;a<t.length;a++){var c=[].concat(t[a]);n&&o[c[0]]||(i&&(c[2]?c[2]="".concat(i," and ").concat(c[2]):c[2]=i),e.push(c))}},e}},591:(t,e,i)=>{"use strict";var n,o=function(){return void 0===n&&(n=Boolean(window&&document&&document.all&&!window.atob)),n},r=function(){var t={};return function(e){if(void 0===t[e]){var i=document.querySelector(e);if(window.HTMLIFrameElement&&i instanceof window.HTMLIFrameElement)try{i=i.contentDocument.head}catch(t){i=null}t[e]=i}return t[e]}}(),s=[];function a(t){for(var e=-1,i=0;i<s.length;i++)if(s[i].identifier===t){e=i;break}return e}function c(t,e){for(var i={},n=[],o=0;o<t.length;o++){var r=t[o],c=e.base?r[0]+e.base:r[0],l=i[c]||0,u="".concat(c," ").concat(l);i[c]=l+1;var d=a(u),m={css:r[1],media:r[2],sourceMap:r[3]};-1!==d?(s[d].references++,s[d].updater(m)):s.push({identifier:u,updater:b(m,e),references:1}),n.push(u)}return n}function l(t){var e=document.createElement("style"),n=t.attributes||{};if(void 0===n.nonce){var o=i.nc;o&&(n.nonce=o)}if(Object.keys(n).forEach((function(t){e.setAttribute(t,n[t])})),"function"==typeof t.insert)t.insert(e);else{var s=r(t.insert||"head");if(!s)throw new Error("Couldn't find a style target. This probably means that the value for the 'insert' parameter is invalid.");s.appendChild(e)}return e}var u,d=(u=[],function(t,e){return u[t]=e,u.filter(Boolean).join("\n")});function m(t,e,i,n){var o=i?"":n.media?"@media ".concat(n.media," {").concat(n.css,"}"):n.css;if(t.styleSheet)t.styleSheet.cssText=d(e,o);else{var r=document.createTextNode(o),s=t.childNodes;s[e]&&t.removeChild(s[e]),s.length?t.insertBefore(r,s[e]):t.appendChild(r)}}function g(t,e,i){var n=i.css,o=i.media,r=i.sourceMap;if(o?t.setAttribute("media",o):t.removeAttribute("media"),r&&"undefined"!=typeof btoa&&(n+="\n/*# sourceMappingURL=data:application/json;base64,".concat(btoa(unescape(encodeURIComponent(JSON.stringify(r))))," */")),t.styleSheet)t.styleSheet.cssText=n;else{for(;t.firstChild;)t.removeChild(t.firstChild);t.appendChild(document.createTextNode(n))}}var p=null,h=0;function b(t,e){var i,n,o;if(e.singleton){var r=h++;i=p||(p=l(e)),n=m.bind(null,i,r,!1),o=m.bind(null,i,r,!0)}else i=l(e),n=g.bind(null,i,e),o=function(){!function(t){if(null===t.parentNode)return!1;t.parentNode.removeChild(t)}(i)};return n(t),function(e){if(e){if(e.css===t.css&&e.media===t.media&&e.sourceMap===t.sourceMap)return;n(t=e)}else o()}}t.exports=function(t,e){(e=e||{}).singleton||"boolean"==typeof e.singleton||(e.singleton=o());var i=c(t=t||[],e);return function(t){if(t=t||[],"[object Array]"===Object.prototype.toString.call(t)){for(var n=0;n<i.length;n++){var o=a(i[n]);s[o].references--}for(var r=c(t,e),l=0;l<i.length;l++){var u=a(i[l]);0===s[u].references&&(s[u].updater(),s.splice(u,1))}i=r}}}},782:(t,e,i)=>{t.exports=i(237)("./src/core.js")},834:(t,e,i)=>{t.exports=i(237)("./src/typing.js")},311:(t,e,i)=>{t.exports=i(237)("./src/ui.js")},237:t=>{"use strict";t.exports=CKEditor5.dll}},e={};function i(n){var o=e[n];if(void 0!==o)return o.exports;var r=e[n]={id:n,exports:{}};return t[n](r,r.exports,i),r.exports}i.n=t=>{var e=t&&t.__esModule?()=>t.default:()=>t;return i.d(e,{a:e}),e},i.d=(t,e)=>{for(var n in e)i.o(e,n)&&!i.o(t,n)&&Object.defineProperty(t,n,{enumerable:!0,get:e[n]})},i.o=(t,e)=>Object.prototype.hasOwnProperty.call(t,e),i.r=t=>{"undefined"!=typeof Symbol&&Symbol.toStringTag&&Object.defineProperty(t,Symbol.toStringTag,{value:"Module"}),Object.defineProperty(t,"__esModule",{value:!0})},i.nc=void 0;var n={};(()=>{"use strict";i.r(n),i.d(n,{Bold:()=>u,BoldEditing:()=>r,BoldUI:()=>l,Code:()=>w,CodeEditing:()=>g,CodeUI:()=>f,Italic:()=>B,ItalicEditing:()=>k,ItalicUI:()=>I,Strikethrough:()=>E,StrikethroughEditing:()=>T,StrikethroughUI:()=>C,Subscript:()=>F,SubscriptEditing:()=>L,SubscriptUI:()=>U,Superscript:()=>j,SuperscriptEditing:()=>V,SuperscriptUI:()=>K,Underline:()=>$,UnderlineEditing:()=>_,UnderlineUI:()=>H});var t=i(782);class e extends t.Command{constructor(t,e){super(t),this.attributeKey=e}refresh(){const t=this.editor.model,e=t.document;this.value=this._getValueFromFirstAllowedNode(),this.isEnabled=t.schema.checkAttributeInSelection(e.selection,this.attributeKey)}execute(t={}){const e=this.editor.model,i=e.document.selection,n=void 0===t.forceValue?!this.value:t.forceValue;e.change((t=>{if(i.isCollapsed)n?t.setSelectionAttribute(this.attributeKey,!0):t.removeSelectionAttribute(this.attributeKey);else{const o=e.schema.getValidRanges(i.getRanges(),this.attributeKey);for(const e of o)n?t.setAttribute(this.attributeKey,n,e):t.removeAttribute(this.attributeKey,e)}}))}_getValueFromFirstAllowedNode(){const t=this.editor.model,e=t.schema,i=t.document.selection;if(i.isCollapsed)return i.hasAttribute(this.attributeKey);for(const t of i.getRanges())for(const i of t.getItems())if(e.checkAttribute(i,this.attributeKey))return i.hasAttribute(this.attributeKey);return!1}}const o="bold";class r extends t.Plugin{static get pluginName(){return"BoldEditing"}init(){const t=this.editor,i=this.editor.t;t.model.schema.extend("$text",{allowAttributes:o}),t.model.schema.setAttributeProperties(o,{isFormatting:!0,copyOnEnter:!0}),t.conversion.attributeToElement({model:o,view:"strong",upcastAlso:["b",t=>{const e=t.getStyle("font-weight");return e&&("bold"==e||Number(e)>=600)?{name:!0,styles:["font-weight"]}:null}]}),t.commands.add(o,new e(t,o)),t.keystrokes.set("CTRL+B",o),t.accessibility.addKeystrokeInfos({keystrokes:[{label:i("Bold text"),keystroke:"CTRL+B"}]})}}var s=i(311);function a({editor:t,commandName:e,plugin:i,icon:n,label:o,keystroke:r}){return s=>{const a=t.commands.get(e),c=new s(t.locale);return c.set({label:o,icon:n,keystroke:r,isToggleable:!0}),c.bind("isEnabled").to(a,"isEnabled"),i.listenTo(c,"execute",(()=>{t.execute(e),t.editing.view.focus()})),c}}const c="bold";class l extends t.Plugin{static get pluginName(){return"BoldUI"}init(){const e=this.editor,i=e.locale.t,n=e.commands.get(c),o=a({editor:e,commandName:c,plugin:this,icon:t.icons.bold,label:i("Bold"),keystroke:"CTRL+B"});e.ui.componentFactory.add(c,(()=>{const t=o(s.ButtonView);return t.set({tooltip:!0}),t.bind("isOn").to(n,"value"),t})),e.ui.componentFactory.add("menuBar:"+c,(()=>o(s.MenuBarMenuListItemButtonView)))}}class u extends t.Plugin{static get requires(){return[r,l]}static get pluginName(){return"Bold"}}var d=i(834);const m="code";class g extends t.Plugin{static get pluginName(){return"CodeEditing"}static get requires(){return[d.TwoStepCaretMovement]}init(){const t=this.editor,i=this.editor.t;t.model.schema.extend("$text",{allowAttributes:m}),t.model.schema.setAttributeProperties(m,{isFormatting:!0,copyOnEnter:!1}),t.conversion.attributeToElement({model:m,view:"code",upcastAlso:{styles:{"word-wrap":"break-word"}}}),t.commands.add(m,new e(t,m)),t.plugins.get(d.TwoStepCaretMovement).registerAttribute(m),(0,d.inlineHighlight)(t,m,"code","ck-code_selected"),t.accessibility.addKeystrokeInfos({keystrokes:[{label:i("Move out of an inline code style"),keystroke:[["arrowleft","arrowleft"],["arrowright","arrowright"]]}]})}}var p=i(591),h=i.n(p),b=i(598),v={injectType:"singletonStyleTag",attributes:{"data-cke":!0},insert:"head",singleton:!0};h()(b.A,v);b.A.locals;const y="code";class f extends t.Plugin{static get pluginName(){return"CodeUI"}init(){const t=this.editor,e=t.locale.t,i=a({editor:t,commandName:y,plugin:this,icon:'<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="m12.5 5.7 5.2 3.9v1.3l-5.6 4c-.1.2-.3.2-.5.2-.3-.1-.6-.7-.6-1l.3-.4 4.7-3.5L11.5 7l-.2-.2c-.1-.3-.1-.6 0-.8.2-.2.5-.4.8-.4a.8.8 0 0 1 .4.1zm-5.2 0L2 9.6v1.3l5.6 4c.1.2.3.2.5.2.3-.1.7-.7.6-1 0-.1 0-.3-.2-.4l-5-3.5L8.2 7l.2-.2c.1-.3.1-.6 0-.8-.2-.2-.5-.4-.8-.4a.8.8 0 0 0-.3.1z"/></svg>',label:e("Code")});t.ui.componentFactory.add(y,(()=>{const e=i(s.ButtonView),n=t.commands.get(y);return e.set({tooltip:!0}),e.bind("isOn").to(n,"value"),e})),t.ui.componentFactory.add("menuBar:"+y,(()=>i(s.MenuBarMenuListItemButtonView)))}}class w extends t.Plugin{static get requires(){return[g,f]}static get pluginName(){return"Code"}}const x="italic";class k extends t.Plugin{static get pluginName(){return"ItalicEditing"}init(){const t=this.editor,i=this.editor.t;t.model.schema.extend("$text",{allowAttributes:x}),t.model.schema.setAttributeProperties(x,{isFormatting:!0,copyOnEnter:!0}),t.conversion.attributeToElement({model:x,view:"i",upcastAlso:["em",{styles:{"font-style":"italic"}}]}),t.commands.add(x,new e(t,x)),t.keystrokes.set("CTRL+I",x),t.accessibility.addKeystrokeInfos({keystrokes:[{label:i("Italic text"),keystroke:"CTRL+I"}]})}}const S="italic";class I extends t.Plugin{static get pluginName(){return"ItalicUI"}init(){const t=this.editor,e=t.commands.get(S),i=t.locale.t,n=a({editor:t,commandName:S,plugin:this,icon:'<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="m9.586 14.633.021.004c-.036.335.095.655.393.962.082.083.173.15.274.201h1.474a.6.6 0 1 1 0 1.2H5.304a.6.6 0 0 1 0-1.2h1.15c.474-.07.809-.182 1.005-.334.157-.122.291-.32.404-.597l2.416-9.55a1.053 1.053 0 0 0-.281-.823 1.12 1.12 0 0 0-.442-.296H8.15a.6.6 0 0 1 0-1.2h6.443a.6.6 0 1 1 0 1.2h-1.195c-.376.056-.65.155-.823.296-.215.175-.423.439-.623.79l-2.366 9.347z"/></svg>',keystroke:"CTRL+I",label:i("Italic")});t.ui.componentFactory.add(S,(()=>{const t=n(s.ButtonView);return t.set({tooltip:!0}),t.bind("isOn").to(e,"value"),t})),t.ui.componentFactory.add("menuBar:"+S,(()=>n(s.MenuBarMenuListItemButtonView)))}}class B extends t.Plugin{static get requires(){return[k,I]}static get pluginName(){return"Italic"}}const A="strikethrough";class T extends t.Plugin{static get pluginName(){return"StrikethroughEditing"}init(){const t=this.editor,i=this.editor.t;t.model.schema.extend("$text",{allowAttributes:A}),t.model.schema.setAttributeProperties(A,{isFormatting:!0,copyOnEnter:!0}),t.conversion.attributeToElement({model:A,view:"s",upcastAlso:["del","strike",{styles:{"text-decoration":"line-through"}}]}),t.commands.add(A,new e(t,A)),t.keystrokes.set("CTRL+SHIFT+X","strikethrough"),t.accessibility.addKeystrokeInfos({keystrokes:[{label:i("Strikethrough text"),keystroke:"CTRL+SHIFT+X"}]})}}const N="strikethrough";class C extends t.Plugin{static get pluginName(){return"StrikethroughUI"}init(){const t=this.editor,e=t.locale.t,i=a({editor:t,commandName:N,plugin:this,icon:'<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M7 16.4c-.8-.4-1.5-.9-2.2-1.5a.6.6 0 0 1-.2-.5l.3-.6h1c1 1.2 2.1 1.7 3.7 1.7 1 0 1.8-.3 2.3-.6.6-.4.6-1.2.6-1.3.2-1.2-.9-2.1-.9-2.1h2.1c.3.7.4 1.2.4 1.7v.8l-.6 1.2c-.6.8-1.1 1-1.6 1.2a6 6 0 0 1-2.4.6c-1 0-1.8-.3-2.5-.6zM6.8 9 6 8.3c-.4-.5-.5-.8-.5-1.6 0-.7.1-1.3.5-1.8.4-.6 1-1 1.6-1.3a6.3 6.3 0 0 1 4.7 0 4 4 0 0 1 1.7 1l.3.7c0 .1.2.4-.2.7-.4.2-.9.1-1 0a3 3 0 0 0-1.2-1c-.4-.2-1-.3-2-.4-.7 0-1.4.2-2 .6-.8.6-1 .8-1 1.5 0 .8.5 1 1.2 1.5.6.4 1.1.7 1.9 1H6.8z"/><path d="M3 10.5V9h14v1.5z"/></svg>',keystroke:"CTRL+SHIFT+X",label:e("Strikethrough")});t.ui.componentFactory.add(N,(()=>{const e=i(s.ButtonView),n=t.commands.get(N);return e.set({tooltip:!0}),e.bind("isOn").to(n,"value"),e})),t.ui.componentFactory.add("menuBar:"+N,(()=>i(s.MenuBarMenuListItemButtonView)))}}class E extends t.Plugin{static get requires(){return[T,C]}static get pluginName(){return"Strikethrough"}}const M="subscript";class L extends t.Plugin{static get pluginName(){return"SubscriptEditing"}init(){const t=this.editor;t.model.schema.extend("$text",{allowAttributes:M}),t.model.schema.setAttributeProperties(M,{isFormatting:!0,copyOnEnter:!0}),t.conversion.attributeToElement({model:M,view:"sub",upcastAlso:[{styles:{"vertical-align":"sub"}}]}),t.commands.add(M,new e(t,M))}}const P="subscript";class U extends t.Plugin{static get pluginName(){return"SubscriptUI"}init(){const t=this.editor,e=t.locale.t,i=a({editor:t,commandName:P,plugin:this,icon:'<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="m7.03 10.349 3.818-3.819a.8.8 0 1 1 1.132 1.132L8.16 11.48l3.819 3.818a.8.8 0 1 1-1.132 1.132L7.03 12.61l-3.818 3.82a.8.8 0 1 1-1.132-1.132L5.9 11.48 2.08 7.662A.8.8 0 1 1 3.212 6.53l3.818 3.82zm8.147 7.829h2.549c.254 0 .447.05.58.152a.49.49 0 0 1 .201.413.54.54 0 0 1-.159.393c-.105.108-.266.162-.48.162h-3.594c-.245 0-.435-.066-.572-.197a.621.621 0 0 1-.205-.463c0-.114.044-.265.132-.453a1.62 1.62 0 0 1 .288-.444c.433-.436.824-.81 1.172-1.122.348-.312.597-.517.747-.615.267-.183.49-.368.667-.553.177-.185.312-.375.405-.57.093-.194.139-.384.139-.57a1.008 1.008 0 0 0-.554-.917 1.197 1.197 0 0 0-.56-.133c-.426 0-.761.182-1.005.546a2.332 2.332 0 0 0-.164.39 1.609 1.609 0 0 1-.258.488c-.096.114-.237.17-.423.17a.558.558 0 0 1-.405-.156.568.568 0 0 1-.161-.427c0-.218.05-.446.151-.683.101-.238.252-.453.452-.646s.454-.349.762-.467a2.998 2.998 0 0 1 1.081-.178c.498 0 .923.076 1.274.228a1.916 1.916 0 0 1 1.004 1.032 1.984 1.984 0 0 1-.156 1.794c-.2.32-.405.572-.613.754-.208.182-.558.468-1.048.857-.49.39-.826.691-1.008.906a2.703 2.703 0 0 0-.24.309z"/></svg>',label:e("Subscript")});t.ui.componentFactory.add(P,(()=>{const e=i(s.ButtonView),n=t.commands.get(P);return e.set({tooltip:!0}),e.bind("isOn").to(n,"value"),e})),t.ui.componentFactory.add("menuBar:"+P,(()=>i(s.MenuBarMenuListItemButtonView)))}}class F extends t.Plugin{static get requires(){return[L,U]}static get pluginName(){return"Subscript"}}const O="superscript";class V extends t.Plugin{static get pluginName(){return"SuperscriptEditing"}init(){const t=this.editor;t.model.schema.extend("$text",{allowAttributes:O}),t.model.schema.setAttributeProperties(O,{isFormatting:!0,copyOnEnter:!0}),t.conversion.attributeToElement({model:O,view:"sup",upcastAlso:[{styles:{"vertical-align":"super"}}]}),t.commands.add(O,new e(t,O))}}const R="superscript";class K extends t.Plugin{static get pluginName(){return"SuperscriptUI"}init(){const t=this.editor,e=t.locale.t,i=a({editor:t,commandName:R,plugin:this,icon:'<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M15.677 8.678h2.549c.254 0 .447.05.58.152a.49.49 0 0 1 .201.413.54.54 0 0 1-.159.393c-.105.108-.266.162-.48.162h-3.594c-.245 0-.435-.066-.572-.197a.621.621 0 0 1-.205-.463c0-.114.044-.265.132-.453a1.62 1.62 0 0 1 .288-.444c.433-.436.824-.81 1.172-1.122.348-.312.597-.517.747-.615.267-.183.49-.368.667-.553.177-.185.312-.375.405-.57.093-.194.139-.384.139-.57a1.008 1.008 0 0 0-.554-.917 1.197 1.197 0 0 0-.56-.133c-.426 0-.761.182-1.005.546a2.332 2.332 0 0 0-.164.39 1.609 1.609 0 0 1-.258.488c-.096.114-.237.17-.423.17a.558.558 0 0 1-.405-.156.568.568 0 0 1-.161-.427c0-.218.05-.446.151-.683.101-.238.252-.453.452-.646s.454-.349.762-.467a2.998 2.998 0 0 1 1.081-.178c.498 0 .923.076 1.274.228a1.916 1.916 0 0 1 1.004 1.032 1.984 1.984 0 0 1-.156 1.794c-.2.32-.405.572-.613.754-.208.182-.558.468-1.048.857-.49.39-.826.691-1.008.906a2.703 2.703 0 0 0-.24.309zM7.03 10.349l3.818-3.819a.8.8 0 1 1 1.132 1.132L8.16 11.48l3.819 3.818a.8.8 0 1 1-1.132 1.132L7.03 12.61l-3.818 3.82a.8.8 0 1 1-1.132-1.132L5.9 11.48 2.08 7.662A.8.8 0 1 1 3.212 6.53l3.818 3.82z"/></svg>',label:e("Superscript")});t.ui.componentFactory.add(R,(()=>{const e=i(s.ButtonView),n=t.commands.get(R);return e.set({tooltip:!0}),e.bind("isOn").to(n,"value"),e})),t.ui.componentFactory.add("menuBar:"+R,(()=>i(s.MenuBarMenuListItemButtonView)))}}class j extends t.Plugin{static get requires(){return[V,K]}static get pluginName(){return"Superscript"}}const z="underline";class _ extends t.Plugin{static get pluginName(){return"UnderlineEditing"}init(){const t=this.editor,i=this.editor.t;t.model.schema.extend("$text",{allowAttributes:z}),t.model.schema.setAttributeProperties(z,{isFormatting:!0,copyOnEnter:!0}),t.conversion.attributeToElement({model:z,view:"u",upcastAlso:{styles:{"text-decoration":"underline"}}}),t.commands.add(z,new e(t,z)),t.keystrokes.set("CTRL+U","underline"),t.accessibility.addKeystrokeInfos({keystrokes:[{label:i("Underline text"),keystroke:"CTRL+U"}]})}}const q="underline";class H extends t.Plugin{static get pluginName(){return"UnderlineUI"}init(){const t=this.editor,e=t.commands.get(q),i=t.locale.t,n=a({editor:t,commandName:q,plugin:this,icon:'<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M3 18v-1.5h14V18zm2.2-8V3.6c0-.4.4-.6.8-.6.3 0 .7.2.7.6v6.2c0 2 1.3 2.8 3.2 2.8 1.9 0 3.4-.9 3.4-2.9V3.6c0-.3.4-.5.8-.5.3 0 .7.2.7.5V10c0 2.7-2.2 4-4.9 4-2.6 0-4.7-1.2-4.7-4z"/></svg>',label:i("Underline"),keystroke:"CTRL+U"});t.ui.componentFactory.add(q,(()=>{const t=n(s.ButtonView);return t.set({tooltip:!0}),t.bind("isOn").to(e,"value"),t})),t.ui.componentFactory.add("menuBar:"+q,(()=>n(s.MenuBarMenuListItemButtonView)))}}class $ extends t.Plugin{static get requires(){return[_,H]}static get pluginName(){return"Underline"}}})(),(window.CKEditor5=window.CKEditor5||{}).basicStyles=n})();