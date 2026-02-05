# NextGen Bank Management System 🏦🚀
> **Revolutionizing Digital Banking with AI-Powered Intelligence.**

Welcome to **NextGen Bank**, a comprehensive Database Management System (DBMS) project that merges secure banking logic with state-of-the-art Artificial Intelligence. From secure transactions to an "Elite AI Assistant," this system represents the future of fintech.

![Status](https://img.shields.io/badge/Status-Live-green) ![Tech](https://img.shields.io/badge/Tech-PHP%20%7C%20Node.js%20%7C%20MySQL-blue) ![AI](https://img.shields.io/badge/AI-Groq%20Llama%203.1-purple)

---

## 🌟 Key Features
*   **🤖 Elite AI Assistant**: A dedicated Llama-3 powered chatbot that provides real-time banking guidance, embedded in both the landing page and dashboard.
*   **🛡️ Secure Authentication**: Multi-role login (Admin, Staff, Customer) with session protection and bcrypt hashing.
*   **💎 Premium UI**: A responsive, Glassmorphism-based design built with **TailwindCSS**.
*   **💸 Smart Transactions**: Real-time fund transfers, bill payments, and robust atomic transaction handling.
*   **📊 Dynamic Dashboard**: Personalized "Command Center" for customers to view assets, cards, and biometrics.

---

## ⚙️ Tech Stack
*   **Frontend**: HTML5, TailwindCSS, JavaScript (Vanilla)
*   **Backend**: PHP (Core)
*   **Database**: MySQL (Relational Schema)
*   **AI Service**: Node.js (Microservice Proxy) + Groq API
*   **Server**: Apache (via XAMPP)

---

## 🚀 Getting Started

Follow these steps to set up the project locally.

### 1. Prerequisites
*   [XAMPP](https://www.apachefriends.org/) (for PHP & MySQL)
*   [Node.js](https://nodejs.org/) (for the AI Service)
*   A [Groq API Key](https://groq.com/) (Free)

### 2. Installation
1.  **Clone the Repository**:
    ```bash
    cd C:\xampp\htdocs
    git clone https://github.com/muqarabOG/NextGen-Bank.git nextgenbank
    ```

2.  **Import the Database**:
    *   Open **phpMyAdmin** (`http://localhost/phpmyadmin`).
    *   Create a new database named `nextgenbank`.
    *   Import the file `MASTER_DATABASE_SYNC.sql` located in the root folder.

3.  **Setup AI Service**:
    *   Navigate to the AI service folder:
        ```bash
        cd nextgenbank/ai_service
        ```
    *   Install dependencies:
        ```bash
        npm install
        ```
    *   **Configure API Key**:
        *   Open the `.env` file (if it doesn't exist, create it).
        *   Add your key:
            ```env
            GROQ_API_KEY=your_actual_api_key_here
            ```

---

## 🖥️ How to Run

### 1. Start the Backend (XAMPP)
*   Open XAMPP Control Panel.
*   Start **Apache** and **MySQL**.
*   Visit the landing page:
    👉 [http://localhost/nextgenbank/index.html](http://localhost/nextgenbank/index.html)

### 2. Start the AI "Brain" (Node.js)
The chatbot needs the Node.js proxy to be running to "think".
```powershell
cd C:\xampp\htdocs\nextgenbank\ai_service
node proxy.js
```
*You should see: `✅ Proxy running at http://127.0.0.1:3000/chat`*

---

## 👤 Default Credentials (for Testing)
*   **Admin Portal**: `/staff/admin.php`
*   **Customer Dashboard**: `/portal/dashboard.php`
*(Check the database `users` table for generated test accounts)*


---

## 🤝 Contributors
*   **Frontend**: Ali Hamid
*   **Database**: Aliyan Waseem
*   **Backend & AI**: Muqarab Nazir

*Built with ❤️ for DBMS Course Project.*
