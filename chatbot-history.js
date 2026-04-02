/* CHATBOT MEMORY SYSTEM */

(function(){

/* LOAD HISTORY */
window.addEventListener("load", function(){

const history = JSON.parse(localStorage.getItem("chatHistory") || "[]");

if(!history.length) return;

history.forEach(msg=>{
const el = document.createElement('div');

el.className = 'cb-msg ' + (msg.role === 'user' ? 'user' : 'ai');

el.innerHTML = `
<div class="cb-avatar">${msg.role === 'user' ? 'ME' : 'AI'}</div>
<div class="cb-bubble">${msg.text}</div>
`;

const body = document.getElementById("chatbotBody");
if(body){
body.appendChild(el);
}

});

});

/* SAVE CHAT WHEN NEW MESSAGE APPEARS */

const observer = new MutationObserver(function(){

const body = document.getElementById("chatbotBody");
if(!body) return;

const messages = body.querySelectorAll(".cb-msg");

let history = [];

messages.forEach(msg=>{

const role = msg.classList.contains("user") ? "user" : "ai";

const text = msg.querySelector(".cb-bubble").innerText;

history.push({
role: role,
text: text
});

});

localStorage.setItem("chatHistory", JSON.stringify(history));

});

window.addEventListener("load",function(){

const body = document.getElementById("chatbotBody");

if(body){
observer.observe(body,{
childList:true,
subtree:true
});
}

});

})();