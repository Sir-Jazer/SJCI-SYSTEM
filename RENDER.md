# Quick deploy to Render (free) — for the demo

Railway is now paid-only. **Render** hosts the exact same `Dockerfile` for free,
straight from your GitHub repo. No credit card needed to start.

> Trade-off to know up front: a free Render web service **sleeps after ~15 min of
> inactivity** and takes ~30–60s to wake on the next visit. So **open the URL a
> minute or two before you present** to warm it up, and it'll be snappy for the demo.

## Steps

1. Push the repo (if you haven't):
   ```
   git add -A && git commit -m "Add Render deploy for demo"
   git push
   ```
2. Go to <https://render.com> → sign up / sign in **with GitHub**.
3. **New + → Web Service** → connect and pick this repository.
   - Render detects the `Dockerfile` automatically.
   - **Instance Type: Free.**
   - (If it asks, Health Check Path = `/up`.)
4. Before the first deploy finishes, open **Environment** and add:
   | Key | Value |
   |---|---|
   | `APP_KEY` | your `base64:...` key (below) |
   | `APP_ENV` | `production` |
   | `APP_DEBUG` | `false` |
5. Click **Create Web Service**. First build takes a few minutes.
6. When it's live, Render gives you a URL like `https://sjci-demo.onrender.com` —
   that's your demo link.
7. *(Optional but recommended)* add `APP_URL` = that URL, then redeploy, so links
   are correct.

### Your APP_KEY
Reuse the key generated earlier (paste into the `APP_KEY` variable — never commit it):
```
base64:qLS5STJZPR+8cwJNThEU+qEwjM50pQIMWWsEfjheCUc=
```
Or make a fresh one any time with `php artisan key:generate --show`.

## Demo logins (seeded automatically)
| Role | Email | Password |
|---|---|---|
| Head Pastor | `headpastor@sjci.test` | `password` |
| Outreach Pastor 1 | `outreach1@sjci.test` | `password` |
| Outreach Pastor 2 | `outreach2@sjci.test` | `password` |

## Good to know
- **Alternative to the dashboard:** this repo includes `render.yaml`, so you can
  instead use **New + → Blueprint** and Render reads all the settings from it
  (you still set the `APP_KEY` secret in the dashboard).
- **Data resets** on redeploy/sleep (free tier has no persistent disk) — but the
  demo data re-seeds on every boot, so the panel is always populated.
- **Turn off seeding** later (once it holds real data) by setting `SEED_DEMO=false`.
- Render's free tier is fine for a demo. Your permanent home is still **Oracle
  Cloud** (`DEPLOYMENT.md`); this is just to get you on screen tomorrow.

## Other free fallbacks (if Render gives trouble)
- **Koyeb** (koyeb.com) — free instance, Docker + GitHub, similar flow.
- **Fly.io** — `fly launch` detects the Dockerfile (asks for a card, has a small
  free allowance; doesn't sleep).