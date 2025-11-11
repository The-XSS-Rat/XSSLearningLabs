# XSS Teaching Lab (The XSS Rat)

**Warning:** This intentionally vulnerable application is for educational use **only**.
Run it in an isolated environment (local VM, private network, or internal Kubernetes cluster).
Do NOT expose to the public internet.

## What is included
- Reflected XSS lab
- Stored XSS lab (SQLite)
- DOM XSS lab
- Blind XSS lab (logger endpoint)
- XSS Filter lab with selectable filter levels
- XSS Contexts explorer
- A list of filter-bypass payloads (increasing difficulty)
- Dockerfile + docker-compose
- Kubernetes manifests for easy deployment

## Quick local Docker
1. Build and run:
   ```
   docker-compose up --build
   ```
2. Visit: http://localhost:8080

## Kubernetes deployment (example)
Apply the Kubernetes manifests in `k8s/` directory (make sure you build & push the docker image or use your image registry).
For quick testing you can `kubectl port-forward svc/xss-lab 8080:80`.

## Files
All files are in this archive. The `src/` folder contains the PHP app. `bypasses/` contains payload examples.

