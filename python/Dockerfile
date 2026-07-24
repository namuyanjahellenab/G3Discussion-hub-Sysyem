# Playwright's headless Chromium needs its actual browser binary plus a long
# list of system shared libraries (libnss3, libatk-bridge2.0, libgtk-3, ...) -
# trying to install those piecemeal under Nixpacks is a known source of
# GLIBC-mismatch failures, so this service builds from Microsoft's own
# Playwright image instead, which already has Chromium + its deps baked in
# for this exact playwright pip version.
FROM mcr.microsoft.com/playwright/python:v1.61.0-jammy

WORKDIR /app

COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

COPY . .

# $PORT is injected by Railway at runtime (not known at build time).
CMD gunicorn --bind 0.0.0.0:$PORT --workers 2 --timeout 60 app:app
