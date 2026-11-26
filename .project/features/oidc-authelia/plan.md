# Plan: oidc-authelia (#2204)

Marketing: entfällt (keine neue Seite, nur ein Knopf auf der vorhandenen Login-Seite)
Design: entfällt (kein Redesign)
PROJEKT: /home/joerg/projekte/packages_dev/packages/userauth
Branch: `feature/oidc-authelia` (Paket-Repo, von `feature/kern-umstellung`), Host-Stapel `stapel/2026-11-26-1316`

## T1 [senior] OIDC-Anmeldung (AK1–AK19, Randfälle 1–5)
— Tests: tests/Feature/Oidc/OidcLoginSeiteTest.php tests/Feature/Oidc/OidcAblaufTest.php tests/Feature/Oidc/OidcIdentityProviderTest.php tests/Feature/Oidc/OidcZuordnungTest.php
Bestehende Tests: tests/Feature/SocialLogin/SocialLoginLayerDependencyTest.php tests/Feature/SocialLogin/SocialLoginAvailabilityTest.php tests/Feature/SocialLogin/SocialLoginRoutesTest.php tests/Feature/SocialLogin/SocialAccountManagementTest.php tests/Feature/PasswordLoginTwoFactorTest.php

Stand: Spec und Plan geschrieben, selbst freigegeben (Automode).
