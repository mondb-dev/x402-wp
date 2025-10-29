# Quick Start Guide

Get up and running with X402 Paywall in minutes!

## Prerequisites

- WordPress 6.0+
- PHP 8.1+
- Composer installed
- A wallet with USDC on supported networks

## 5-Minute Setup

### Step 1: Install the Plugin

```bash
cd wp-content/plugins/
git clone https://github.com/mondb-dev/x402-wp.git x402-paywall
cd x402-paywall
composer install --no-dev
```

### Step 2: Activate

1. Go to **Plugins** in WordPress admin
2. Find "X402 Paywall" and click **Activate**

### Step 3: Configure Your Payment Address

1. Go to **Users → Your Profile**
2. Scroll to **X402 Paywall Payment Addresses**
3. Enter your wallet address:
   - **EVM Address** (0x...): For Base, Ethereum, etc.
   - **Solana Address**: For Solana payments

### Step 4: Create Your First Paywall

1. Create or edit a post
2. Find the **X402 Paywall Settings** box (right sidebar)
3. Check **Enable Paywall**
4. Configure:
   - Network Type: EVM or Solana
   - Network: Base Mainnet (recommended for low fees)
   - Token: USDC
   - Amount: 1.00
5. Click **Publish** or **Update**

### Step 5: Test It Out

1. Log out or open an incognito window
2. Visit your paywalled post
3. You should see the paywall message with payment details

That's it! Your content is now protected with a crypto paywall.

## Common Networks & Tokens

### Base Mainnet (Recommended)
- **Why**: Low fees, fast confirmation
- **USDC Address**: `0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913`
- **Your wallet needs**: Base network added to MetaMask

### Solana Mainnet
- **Why**: Very fast, low fees
- **USDC Address**: `EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v`
- **Your wallet needs**: Phantom or Solflare wallet

### For Testing
- Use **Base Sepolia** (testnet)
- Get free testnet USDC from faucets

## Next Steps

- [Configure global settings](https://github.com/mondb-dev/x402-wp#plugin-settings)
- [Read full documentation](https://github.com/mondb-dev/x402-wp#readme)
- [Learn about x402 protocol](https://x402.gitbook.io/x402)

## Troubleshooting

### "Required dependencies are missing"

Run in plugin directory:
```bash
composer install --no-dev
```

### "Invalid address format"

- EVM addresses start with `0x` (42 characters total)
- Solana addresses are 32-44 base58 characters

### Can't see the meta box

Make sure you have **Author** role or higher. Contributors cannot create paywalls.

## Support

- [GitHub Issues](https://github.com/mondb-dev/x402-wp/issues)
- [Full Documentation](https://github.com/mondb-dev/x402-wp#readme)
