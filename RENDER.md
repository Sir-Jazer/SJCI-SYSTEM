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
   | Key | Value | Notes |
   |---|---|---|
   | `APP_KEY` | your `base64:...` key (below) | required |
   | `APP_ENV` | `production` | |
   | `APP_DEBUG` | `false` | |
   | `SEED_DEMO` | `false` | **empty database** (only your admin login). Use `true` for demo data. |
   | `ADMIN_EMAIL` | your email | the Head Pastor login on the empty start |
   | `ADMIN_PASSWORD` | *(set as secret)* | if omitted, defaults to `password` and forces a change on first login |
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

## Logins
- **Empty start** (`SEED_DEMO=false`, the default): log in as your `ADMIN_EMAIL`
  with your `ADMIN_PASSWORD` (or `password`, which forces a change on first login).
  Everything else is empty — create churches, pastors, and records yourself.
- **Demo data** (`SEED_DEMO=true`): seeded accounts, all with password `password` —
  `headpastor@sjci.test`, `outreach1@sjci.test`, `outreach2@sjci.test`.

## Where is the database, and does it persist?
- The live database is a SQLite file **inside the container at `/data/database.sqlite`**
  (locally on your Mac it's `database/database.sqlite`).
- ⚠️ **Render's free tier has no persistent disk, so `/data` is wiped on every
  redeploy and every wake-from-sleep.** That's why the app rebuilds the database
  on boot (runs migrations, then either seeds demo data or creates just your admin).
  Fine for a demo — **but anything you enter live will not survive a restart.**
- **To keep real data**, mount a persistent disk: Render service → **Settings →
  Disks → Add Disk**, mount path **`/data`** (1 GB is plenty). Render disks are a
  paid add-on. Once mounted, the database survives restarts and the boot-time
  seeding is skipped automatically (it only runs on an empty `/data`).
- Your free, permanent home for real records is still **Oracle Cloud**
  (`DEPLOYMENT.md`).

## Good to know
- **Alternative to the dashboard:** this repo includes `render.yaml`, so you can
  instead use **New + → Blueprint** and Render reads all the settings from it
  (you still set the `APP_KEY`/`ADMIN_PASSWORD` secrets in the dashboard).
- **Fresh start any time:** with no disk, any redeploy resets to a clean database.

## Other free fallbacks (if Render gives trouble)
- **Koyeb** (koyeb.com) — free instance, Docker + GitHub, similar flow.
- **Fly.io** — `fly launch` detects the Dockerfile (asks for a card, has a small
  free allowance; doesn't sleep).