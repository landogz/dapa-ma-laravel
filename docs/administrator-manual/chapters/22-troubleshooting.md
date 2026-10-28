# 22. Troubleshooting and security

## 22.1 Troubleshooting

| Symptom | What to try |
|---|---|
| Sign In does nothing / error under the field | Check email spelling. Use Forgot password. Confirm you are on `/admin/login`. |
| “You do not have permission” | Your role does not include that menu. Ask a Super Admin. |
| List is empty | Clear Search. Wait for load. Check filters (All types / All statuses). |
| Address suggestions do not appear | Type at least 3 letters of a Philippine place. Do not paste a foreign address. |
| Phone will not save | Use a Philippine mobile or landline. Hotlines may use short codes and `\|`. |
| Password rejected | 8+ characters, upper and lower case, a number, a symbol. Do not reuse the last 5 passwords. Avoid known leaked passwords. |
| Forced to change password | An admin set a temporary password, or it expired after 90 days. Set a new one on Edit Profile. |
| Comment will not delete | Open View on the post → Comments → Delete, then confirm. |
| Numbered lists look wrong in Body | Use the editor’s Numbered List button. Do not type `1.` as plain text only. |
| App still shows old label | Edit **App Translations**, not Posts. Wait up to 20 seconds or reopen the app. |
| Map pin missing on rehab save | Search, pick a suggestion, or tap the map so region and province fill. |
| Toast error after save | Read the message. Required fields are marked with *. |

## 22.2 Security checklist

- [ ] Super Admin accounts are limited to people who must manage Users and Journal.
- [ ] Staff who only write posts are **Editor**; content + directories are **Publisher**.
- [ ] When someone leaves, delete the user the same day.
- [ ] Temporary passwords are unique and force a change on first sign-in.
- [ ] You sign out on shared computers.
- [ ] You do not send passwords by unsecured chat. Prefer a password reset email.
- [ ] Legal pages (Privacy Policy, Terms, Privacy Notice) stay accurate in English and Tagalog.
- [ ] You do not paste secrets, database passwords, or installer credentials into this admin.
