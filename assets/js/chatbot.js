/* chatbot.js */
(function () {
    // Inject HTML
    const html = `
    <div id="ngb-chat-trigger">
        <i class="fa fa-comment-dots"></i>
    </div>
    <div id="ngb-chat-window">
        <div class="chat-header">
            <i class="fa fa-robot"></i>
            <div>
                <h4>NextGen Assistant</h4>
                <div class="typing" id="aiTyping">Analyzing Signal...</div>
            </div>
        </div>
        <div class="chat-messages" id="chatMsgs">
            <div class="message msg-ai">
                Hello. I am the NextGen AI Node. How can I assist your operation today?
            </div>
        </div>
        <div class="chat-input-area">
            <form id="chatForm">
                <div class="input-wrapper">
                    <input type="text" id="chatInput" placeholder="Message assistant..." autocomplete="off">
                    <button type="submit" id="chatSend" style="background:none; border:none; color:inherit; cursor:inherit;">
                        <i class="fa fa-paper-plane"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>`;

    const chatDiv = document.createElement('div');
    chatDiv.innerHTML = html;
    document.body.appendChild(chatDiv);

    const trigger = document.getElementById('ngb-chat-trigger');
    const chatWindow = document.getElementById('ngb-chat-window');
    const form = document.getElementById('chatForm');
    const input = document.getElementById('chatInput');
    const msgs = document.getElementById('chatMsgs');
    const typing = document.getElementById('aiTyping');

    trigger.onclick = () => chatWindow.classList.toggle('active');

    form.onsubmit = function (e) {
        e.preventDefault();
        const msg = input.value.trim();
        if (!msg) return;

        // User message
        appendMessage('user', msg);
        input.value = '';

        // Show typing
        typing.classList.add('active');

        // Send to PHP bridge
        const pathPrefix = window.location.pathname.includes('/portal/') ? '../' : '';
        const apiPath = pathPrefix + 'backend/chatbot_api.php';

        console.log("Chatbot: Sending to " + apiPath, { message: msg });

        fetch(apiPath, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ message: msg })
        })
            .then(res => {
                console.log("Chatbot: Response received", res.status);
                return res.json();
            })
            .then(data => {
                console.log("Chatbot: Data received", data);
                typing.classList.remove('active');
                appendMessage('ai', data.reply);
            })
            .catch(err => {
                console.error("Chatbot: Fetch Error", err);
                typing.classList.remove('active');
                appendMessage('ai', 'Communication fault. (Check Browser Console for details)');
            });
    };

    function appendMessage(role, text) {
        const div = document.createElement('div');
        div.className = `message msg-${role}`;

        if (role === 'ai') {
            // Smart Formatter for AI responses
            let htmlContent = text
                .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>') // Handle Bold **text**
                .replace(/\n• (.*?)/g, '<br>• $1')             // Handle Bullets
                .replace(/\n(\d+\.) (.*?)/g, '<br>$1 $2')     // Handle Numbered Lists
                .replace(/\n\n/g, '<br><br>');                 // Handle Double Spacing

            div.innerHTML = htmlContent;
        } else {
            div.innerText = text;
        }

        msgs.appendChild(div);
        msgs.scrollTop = msgs.scrollHeight;
    }
})();
