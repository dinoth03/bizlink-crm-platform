# bizlink-crm-platform

BizLink CRM Platform - A web-based Customer Relationship Management (CRM) platform designed to support customer data management, vendor coordination, and marketplace integration using modern web technologies.

## CI/CD Pipeline (GitHub Actions)

This repository now includes a GitHub Actions pipeline at `.github/workflows/ci-cd.yml`.

### What it does

1. CI on pull requests to `main`: lint PHP files in `api/` using `php -l`.
2. CI on pull requests to `main`: check JavaScript syntax for all `.js` files using `node --check`.
3. CD on push to `main`: run only after CI passes.
4. CD on push to `main`: deploy files to your server over SSH.
5. CD on push to `main`: automatically skip deployment if required secrets are not configured.

### Step-by-step setup

1. Push this repository to GitHub.
2. In GitHub, open your repository.
3. Go to **Settings > Secrets and variables > Actions > New repository secret**.
4. Add secret `DEPLOY_HOST` (example: `your.server.com`).
5. Add secret `DEPLOY_USER` (example: `ubuntu` or your hosting SSH user).
6. Add secret `DEPLOY_SSH_PRIVATE_KEY` (your private key content).
7. Add secret `DEPLOY_PORT` (usually `22`).
8. Add secret `DEPLOY_PATH` (absolute path on the server, example: `/var/www/html/bizlink-crm-platform`).
9. Commit and push changes to `main`.
10. Open the **Actions** tab in GitHub and verify `Quality Checks` job passes.
11. Verify `Deploy to Server` runs, or intentionally skips if secrets are not set.

### How to generate an SSH key for deployment

Run locally:

```bash
ssh-keygen -t ed25519 -C "github-actions-deploy" -f ~/.ssh/bizlink_deploy_key
```

Then:

1. Add the public key (`bizlink_deploy_key.pub`) to your server user's `~/.ssh/authorized_keys`.
2. Copy the private key content (`bizlink_deploy_key`) into the `DEPLOY_SSH_PRIVATE_KEY` GitHub secret.

### Notes

- Keep sensitive values only in GitHub Secrets, never in code.
- Current deployment excludes `.git`, `.github`, and `.vscode`.
- If your host does not allow SSH-based deploys, this workflow can be adapted to FTP/SFTP.
