# LumeCore 🛡️ 🚀

**AI-Powered Mergers & Acquisitions (M&A) Forensic & Security Ecosystem**

LumeCore (LUME) is a high-performance auditing and forensic engine designed for the modern M&A landscape. Built on Laravel 12 and powered by Google Gemini 2.0, it provides deep insights into codebases, infrastructure, and asset health, enabling transparent and secure technology transfers.

---

## 🌟 Key Pillars

### 1. 🔍 Infrastructure & Codebase Forensics (Titan Engine)
The **Titan Engine** performs deep-dive audits into any web project or repository:
- **Tech Footprint Sniffing**: Automatically identifies frameworks, databases, and third-party services using both repo analysis and live browser forensics (via Puppeteer).
- **DNS & SSL Audit**: Inspects nameservers, SSL certificate health, and security headers.
- **Git Churn & Toxicity**: Analyzes commit history to identify "toxic" files (high churn + large size) and technical debt.
- **Bus Factor Analysis**: Determines author distribution and knowledge silos to assess organizational risk.
- **Sovereign Fingerprinting**: Generates a unique SHA-256 fingerprint of the codebase's state to ensure integrity during transfers.

### 2. 🤖 AI Architect (Gemini Integration)
Integrated with **Google Gemini 2.0 Flash**, LumeCore provides:
- **Smart Config Analysis**: Automatically identifies required environment variables and secrets by analyzing `composer.json`, `package.json`, and PHP config files.
- **Deep Insight Chat**: An AI-powered chat interface that understands your project's context and helps navigate complex audits.
- **Automated Reporting**: Generates comprehensive audit findings and forensic summaries.

### 3. ☁️ CloudVault & Asset Management
- **Secure Auditing**: Upload and scan documents and assets in a secure, isolated environment.
- **Asset Health Logs**: Real-time tracking of asset status and audit history.
- **Embedding-based Search**: Uses `pgvector` to store and search asset embeddings for high-relevance retrieval.

### 4. 🛒 M&A Marketplace & Ledger
- **Asset Monetization**: List verified project assets for sale.
- **Escrow & Transactions**: Integrated wallet and transaction system for secure acquisitions.
- **Ownership Verification**: Automated tools to verify website and repository ownership before listing.

---

## 🛠️ Tech Stack

- **Backend**: [Laravel 12](https://laravel.com/) (PHP 8.2+)
- **Frontend**: [Vue 3](https://vuejs.org/) + [Inertia.js](https://inertiajs.com/) + [Tailwind CSS](https://tailwindcss.com/)
- **Admin Panel**: [Filament v3](https://filamentphp.com/)
- **AI Engine**: [Google Gemini 2.0 Flash](https://deepmind.google/technologies/gemini/)
- **Real-time**: [Laravel Reverb](https://reverb.laravel.com/) (WebSockets)
- **Database**: PostgreSQL with [pgvector](https://github.com/pgvector/pgvector)
- **Browser Automation**: [Spatie Browsershot](https://github.com/spatie/browsershot) / Puppeteer
- **Search**: Meilisearch

---

## 🚀 Getting Started

### Prerequisites
- PHP 8.2+
- Node.js & NPM
- PostgreSQL with `pgvector`
- Google Chrome (for Browsershot)
- Python 3.10+ (for certain security scanning tools)

### Installation

1. **Clone the repository**:
   ```bash
   git clone https://github.com/EisenDev/LumeCore.git
   cd LumeCore
   ```

2. **Install dependencies**:
   ```bash
   composer install
   npm install
   ```

3. **Configure Environment**:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
   *Note: Ensure you set `GEMINI_API_KEY`, `DB_CONNECTION=pgsql`, and other service credentials.*

4. **Database Setup**:
   ```bash
   php artisan migrate
   ```

5. **Build Assets**:
   ```bash
   npm run build
   ```

6. **Run the Application**:
   ```bash
   php artisan serve
   ```

---

## 🧪 Security Scanning Engine
The surface-level security scanner requires specific Python dependencies:
```bash
pip install -r app/Services/Python/requirements.txt
```

---

## 📄 License
The LumeCore framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

---
*Developed with 💡 by EisenDev*
