# webvimark/module-user-management — PHP 8.5 Patch Notes

Drop these files over the originals in your vendor directory (or better: fork the package and commit them there).

## Files patched

### composer.json
- Added `"php": ">=8.1"` constraint
- Replaced abandoned `ikimea/browser:1.12.0` with `cbschuld/browser.php:^1.9`
  (same API surface — if you use `Browser` anywhere in your own code, no changes needed)

### components/UserConfig.php
- Replaced `@Yii::$app->user->identity->superadmin` error-suppression with nullsafe `?->` operator
- Added return types: `getIsSuperadmin(): bool`, `getUsername(): ?string`, `afterLogin(): void`

### models/User.php
- Added `#[\AllowDynamicProperties]` attribute (PHP 8.2 removed implicit dynamic properties)
- Typed all public properties (`string`, `?string`)
- Replaced `TimestampBehavior::className()` and `Role::className()` with `::class` (className() deprecated in 8.x)
- Added return types to ALL methods
- Changed `AND` / `!= 'cli'` to `&&` / `!== 'cli'` (modern style, same semantics)
- `getStatusValue()` now uses null-coalescing `??` instead of `isset()` ternary

### components/AuthHelper.php
- Replaced `InvalidParamException` (removed in Yii 2.0.14+) with `InvalidArgumentException`
- Replaced all `strpos($x, $y) === 0` checks with `str_starts_with()` (PHP 8.0+)
- Replaced `strpos($x, $y) === false` checks with `!str_contains()` where applicable
- Replaced `strcmp(substr($file, -14), 'Controller.php') === 0` with `str_ends_with()`
- Added return types to ALL methods (public and private)
- Cleaned up the `getRouteRecursive` wildcard route logic (was a dangling `else` producing `//*`)

### UserManagementModule.php
- Added scalar types to all public properties (`bool`, `int`, `string`, `array`)
- Added return types to ALL methods: `init(): void`, `menuItems(): array`, `t(): string`,
  `checkAttempts(): bool`, `prepareMailerOptions(): void`
- Changed `UserManagementModule::t(...)` self-references in `menuItems()` to `self::t(...)`

## What is NOT patched here (needs manual attention)

1. **All controllers** — every controller that overrides `behaviors()`, `rules()`, or
   action methods will need the same return-type treatment. Pattern is identical to what
   was done in User.php.

2. **All view files** — views are plain PHP; no type issues, but check for any
   `${variable}` string interpolation (removed in PHP 8.2). Replace with `{$variable}`.

3. **ikimea/browser usage** — search your codebase for `new Browser()` or `use Browser;`
   and verify the drop-in replacement works:
   ```bash
   grep -r "Browser" vendor/webvimark/
   ```

4. **webvimark/* sub-packages** — the patch only covers `module-user-management`.
   The helper packages (`webvimark/helpers`, `webvimark/components`, etc.) may have
   their own PHP 8.x issues. Run your test suite with `E_DEPRECATED` enabled to surface them.

## How to apply

### Option A — vendor overlay (quick test)
Copy the patched files directly over the originals:
```
vendor/webvimark/module-user-management/composer.json
vendor/webvimark/module-user-management/UserManagementModule.php
vendor/webvimark/module-user-management/components/UserConfig.php
vendor/webvimark/module-user-management/components/AuthHelper.php
vendor/webvimark/module-user-management/models/User.php
```

### Option B — fork (recommended for production)
1. Fork `webvimark/user-management` on GitHub
2. Apply these files to your fork
3. Update your `composer.json` to point at your fork:
   ```json
   "repositories": [
       {
           "type": "vcs",
           "url": "https://github.com/YOUR_FORK/user-management"
       }
   ],
   "require": {
       "webvimark/module-user-management": "dev-master"
   }
   ```

## Smoke-test checklist after applying
- [ ] Login / logout works
- [ ] Registration works (if enabled)
- [ ] RBAC role assignment works
- [ ] Permission checks (`User::hasRole`, `User::canRoute`) work
- [ ] No deprecation warnings in PHP error log (`E_DEPRECATED | E_STRICT`)
