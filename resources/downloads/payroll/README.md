# Payroll rate editions

CRA's payroll deduction tables, one file per edition, served as plain static JSON.

The desktop app fetches `{host}/resources/downloads/payroll/{edition}.json` the first time a pay run needs an edition it does not hold. Dropping a file in here is what makes a CRA changeover reach customers without an app release.

## Naming

`YYYY-01.json` for the edition effective January 1, `YYYY-07.json` for July 1. The app derives the name from the pay date, so it has to be exact. Nothing else in this directory is read.

## Before uploading

The app validates whatever it downloads and ignores anything that fails, so a bad file here is not dangerous, but it IS invisible: the customer just keeps seeing "no tables loaded" and nothing says why. Check before uploading rather than after.

The file must:

- carry the same `editionId` as its filename, or the app refuses it
- have every derived maximum reproduce from its own rate (`CPP max = (YMPE − exemption) × rate`, and the same shape for CPP2, EI, QPP and QPIP)
- have tax brackets that ascend, end open, and meet at every boundary

`PayrollRateValidator` in the desktop repository is the same check the app runs, and `PayrollRateValidatorTests` shows what each failure looks like.

## Preparing an edition

Full process, including where the numbers come from and how to verify them against CRA, is in `docs/Payroll rate updates.md` in the desktop repository. Commit the file there as well as uploading it here: the upload reaches existing installs, the commit means a fresh install has it offline.

## What is here

The 2026 editions, which every current build already carries embedded. They are uploaded anyway so the path is live and provably working rather than first exercised on a deadline.
