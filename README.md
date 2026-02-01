# CVerify 🛡️

### "Don't Trust. Verify."

**The Decentralized Protocol for Professional Credential Verification.**

[![Hackathon](https://img.shields.io/badge/Hackathon-Project-orange?style=flat-square)](https://github.com/ma13w/cverify)
[![PHP](https://img.shields.io/badge/PHP-8.2-777BB4?style=flat-square&logo=php&logoColor=white)](https://www.php.net/)
[![Crypto](https://img.shields.io/badge/Security-RSA__2048-green?style=flat-square)](https://openssl.org/)
[![License](https://img.shields.io/badge/License-MIT-blue?style=flat-square)](https://github.com/ma13w/cverify/blob/main/LICENSE)

---

## 🚨 The Problem: The "LinkedIn Lie"

In the current digital professional landscape, **truth is optional**.

- **Fabricated Profiles:** Anyone can claim to work at Google, Tesla, or OpenAI on their LinkedIn profile without zero proof.
- **Centralized Gatekeepers:** Your professional reputation is locked inside walled gardens (LinkedIn, Indeed) that monetize your data.
- **Expensive Verification:** Companies spend billions annually on background checks that take weeks to complete manually.

**We need a way to prove _Who_ we are and _Where_ we worked, without relying on a central authority.**

---

## 💡 The Solution: CVerify

CVerify is a **decentralized, trustless protocol** that allows companies to cryptographically sign work experiences.

Instead of trusting a platform, we trust **Mathematics**.

### How we fix it without a central authority:

1.  **The Anchor:** Every company already owns a Domain Name (e.g., `google.com`). We use this as the Root of Trust.
2.  **The Signature:** When you leave a job, the company signs a digital attestation (JSON) using their **Private Key**.
3.  **The Verification:** Anyone (recruiters, other companies) can fetch the company's **Public Key** from their DNS records and mathematically verify the signature.

If the math matches, the experience is real. No phone calls, no middleman, no lies.

---

## 🌐 Web3 & Decentralization Perspective

CVerify brings the principles of **Self-Sovereign Identity (SSI)** to the professional world, but without the friction of blockchain gas fees or wallets.

- **User Ownership:** You hold your credentials (as JSON files signed by issuers). You can host them anywhere—on a personal site, IPFS, or a USB drive.
- **DNS-based PKI:** We utilize the Domain Name System (DNS) as a decentralized public key infrastructure. It is the most robust, distributed database in the world.
- **Permissionless:** No API keys required. No "Login with LinkedIn". The protocol is open, standard, and free.

This represents the transition from **Web 2.0 (Platform-centric identity)** to **Web3 (User-centric identity)**.

---

## 🔐 Cryptography & Security Architecture

Security is not an afterthought; it is the core product.

### 1. The algorithms

- **Signing:** RSA-2048 (Probabilistic Signature Scheme).
- **Hashing:** SHA-256 for document fingerprinting.
- **Transport:** JSON payloads encoded in Base64.

### 2. The Chain of Trust

1.  **Key Generation:** The company generates an RSA Keypair locally.
2.  **DNS Publication:** The company publishes the Public Key to a TXT record:
    ```
    _cverify.company.com TXT "cverify-key-0=MIIBIjANBgkqh..."
    ```
3.  **Attestation:** The User sends a request (CSR style). The Company signs the hash of the experience data.
4.  **Verification:**
    $$ Verify(Public*{Key}, Signature, Hash*{Data}) = \text{TRUE} $$

### 3. Attack Resistance

- **Anti-Tamper:** Changing a single character in the job description or dates invalidates the signature immediately.
- **Anti-Spoof:** Only the entity that controls the DNS for `apple.com` can sign valid attestations for Apple.

---

## 🚀 Usage & Flow

The system is composed of three portals:

### 1. User Dashboard ( The Holder )

- Generate personal RSA keys.
- Create a CV profile.
- Request validation for experiences from companies.
- **Result:** A `cv.json` file containing signed attestations.

### 2. Company Portal ( The Issuer )

- Setup corporate identity.
- Publish public keys via DNS.
- Receive pending requests.
- **Action:** Sign legitimate experiences with one click.

### 3. Verifier Lens ( The Observer )

- A public tool requiring no login.
- Input a User's Profile URL.
- **Result:** Real-time cryptographic verification of every single claim.

---

## 🎮 Live Demo

The protocol is live for testing! You can try the full flow right now:

### 1. User Dashboard (The Applicant)

- **URL:** [http://calimatteo.cv:5000](http://calimatteo.cv:5000)
- **Use these credentials to login:**
  - **Private Key:** [Download here](https://github.com/ma13w/cverify/blob/main/test/private_key.pem)
  - **Passphrase:** `supp`

### 2. Company Portal (The Issuer)

- **URL:** [http://wb.info.wf:5000](http://wb.info.wf:5000)
- **Use these credentials to login:**
  - **Private Key:** [Download here](https://github.com/ma13w/cverify/blob/main/test/company_private_key.pem)
  - **Passphrase:** `ppus`

### 3. Verifier Lens (The HR / Recruiter)

- **URL:** [http://cverify.cv:8080](http://cverify.cv:8080)
- **Action:** Paste the profile URL to verify signatures.

---

## 🛠️ Installation

To run the full suite locally:

```bash
# 1. Clone the repo
git clone https://github.com/ma13w/cverify.git

# 2. Start the internal server
cd /public
php -S localhost:8000

# 3. Visit the website
http://localhost:8000 in your browser
```

## 🏆 Why CVerify Is Different

- **Solves a Real Problem:** Resume fraud costs billions.
- **Technically Sound:** Uses standard crypto primitives (OpenSSL) correctly.
- **Decentralized:** True peer-to-peer trust model via DNS.
- **Implementation Ready:** Full working flow from Request -> Sign -> Verify.

---

### _In Math We Trust._
