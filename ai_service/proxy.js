const express = require("express");
const fetch = (...args) => import('node-fetch').then(({ default: fetch }) => fetch(...args));
const cors = require("cors");

const app = express();
app.use(cors());
app.use(express.json());

// ⚠️ Move this to .env later (for production)
const GROQ_API_KEY = "INSERT_YOUR_GROQ_API_KEY_HERE";

app.post("/chat", async (req, res) => {
    try {
        console.log("Incoming:", req.body);

        if (!req.body.message) {
            return res.json({ reply: "Please type a message." });
        }

        const response = await fetch(
            "https://api.groq.com/openai/v1/chat/completions",
            {
                method: "POST",
                headers: {
                    "Authorization": `Bearer ${GROQ_API_KEY}`,
                    "Content-Type": "application/json"
                },
                body: JSON.stringify({
                    model: "llama-3.1-8b-instant",
                    temperature: 0.6,
                    messages: [
                        {
                            role: "system",
                            content: `
You are the "NextGen Elite AI Assistant", a high-performance cognitive node within the NextGen Bank Management System.

CORE DIRECTIVES:
- Maintain an ELITE, SOPHISTICATED, and HYPER-PROFESSIONAL tone.
- Use structured, analytical formatting.
- Deliver responses that feel premium and intelligent.
- No emojis, no slang.

FORMATTING PROTOCOL (CRITICAL):
- Always start with a BOLD TITLE (e.g., **SYSTEM ANALYSIS**).
- Use **bold text** for key terms, amounts, and critical actions.
- Use bullet points (•) or numbered lists for multi-point data.
- Ensure each bullet point is maximum 1.5 lines.
- Limit total response to 3-4 distinct points.
- Use clear white space between sections (use double newlines).

SUPPORT DOMAINS:
- Account Lifecycle Management
- Secure Fund Transmission
- Multi-Card Issuance & Security
- Complaint Resolution Protocols
- Operational Dashboard Navigation

RESTRICTIONS:
- Do not provide sensitive private data unless explicitly linked to the User Context.
- Never acknowledge you are an AI; act as an integrated System Module.
- If unsure, provide a high-level operational summary and ask for categorical clarification.
`
                        },
                        {
                            role: "user",
                            content: req.body.message
                        }
                    ]
                })
            }
        );

        const data = await response.json();
        console.log("Groq reply:", data);

        if (data.error) {
            return res.json({
                reply: "Groq Error: " + data.error.message
            });
        }

        let reply = data.choices[0].message.content || "No response received.";

        // 🧹 Cleanup formatting
        reply = reply
            .replace(/\n{3,}/g, "\n\n")
            .trim();

        res.json({ reply });

    } catch (err) {
        console.error("Proxy error:", err);
        res.status(500).json({
            reply: "Internal AI service error. Please try again."
        });
    }
});

app.listen(3000, "127.0.0.1", () => {
    console.log("✅ Proxy running at http://127.0.0.1:3000/chat");
});
