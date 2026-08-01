# Quick deploy to Railway (for the demo)

A fast, temporary way to get SJCI online for a live demo. Railway builds the
included `Dockerfile` straight from your GitHub repo — no server to manage.

> This image was **built and run locally to confirm it boots, migrates, seeds
> demo data, and serves a styled login page.** It's ready to push.

## Before you start
1. Commit and push these new files to GitHub:
   ```
   git add -A && git commit -m "Add Railway/Docker deploy for demo"
   git push
   ```
2. Have your `APP_KEY` ready. Generate one locally any time with:
   ```
   php artisan key:generate --show
   ```
   Copy the whole `base64:...` string. **Don't commit it** — you'll paste it into
   Railway as a variable.

## Deploy (Railway dashboard — easiest)
1. Go to <https://railway.app> → sign in with GitHub.
2. **New Project → Deploy from GitHub repo** → pick this repository. Railway sees
   `railway.json` + `Dockerfile` and starts building automatically.
3. Open the service → **Variables** → add:
   | Variable | Value |
   |---|---|
   | `APP_KEY` | the `base64:...` string you generated |
   | `APP_ENV` | `production` |
   | `APP_DEBUG` | `false` |

   *(The database path and demo seeding are handled automatically by the startup
   script — nothing else is required to go live.)*
4. **Settings → Networking → Generate Domain.** Railway gives you a public
   `https://…up.railway.app` URL.
5. *(Recommended)* Add the domain as a variable so links/emails are correct:
   add `APP_URL` = your `https://…up.railway.app` URL, then redeploy.
6. Open the URL → you're on the SJCI login page. 🎉

## Demo logins (seeded automatically)
| Role | Email | Password |
|---|---|---|
| Head Pastor | `headpastor@sjci.test` | `password` |
| Outreach Pastor 1 | `outreach1@sjci.test` | `password` |
| Outreach Pastor 2 | `outreach2@sjci.test` | `password` |

The database comes pre-filled with realistic demo data (weekly offerings/tithes,
approvals queue, a correction, quarterly Tithes-of-Tithes, expenses) so there's
plenty to show without setting anything up.

## Good to know
- **Data resets on redeploy.** Without a persistent disk, each new build starts
  from a fresh, freshly-seeded database. For the demo that's usually a plus
  (always clean). If you want data (including anything you add live) to survive
  restarts: **Service → Settings → Volumes → add a volume mounted at `/data`.**
- **Turn off auto-seeding** once it should hold real data: set variable
  `SEED_DEMO` = `false` (only matters on a fresh database / with a volume).
- **Password-reset emails:** still `log`-only unless you add SMTP variables
  (`MAIL_MAILER=smtp`, `MAIL_HOST`, …). Not needed for a demo.
- **Cost:** Railway's trial gives a small usage credit — fine for a short-lived
  demo. When you're done, **pause or delete** the service so it stops consuming
  credit. Your real, permanent home is still Oracle Cloud (see `DEPLOYMENT.md`).

## If a build/boot fails
Check the **Deploy Logs** in Railway. The most common causes are a missing
`APP_KEY` variable (add it) or a healthcheck timeout (the app answers on `/up`,
already configured in `railway.json`).