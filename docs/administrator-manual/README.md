# DAPE-MA Administrator Manual — source

Staff-facing Word manual for the DAPE-MA Admin Control Panel.

| Output | Path |
|--------|------|
| Word document | `docs/DAPE-MA-ADMINISTRATOR-MANUAL.docx` |
| Markdown chapters | `docs/administrator-manual/chapters/` |
| Screenshots | `docs/administrator-manual/figures/` |

## Rebuild the Word file

From `dape-ma-laravel`:

```bash
python3 docs/administrator-manual/build_docx.py
```

Requires `python-docx` (`pip3 install python-docx`). After opening the Word file, right-click the Contents field and choose **Update Field** if you want Word’s automatic page numbers. A chapter list is already printed on that page.

## Chapter map (sidebar order)

1. How to sign in
2. Administrator duties
3. How lists work
4. Dashboard
5. Posts
6. Contests
7. IEC Materials
8. Rehab Centers
9. Care Toolkit
10. Care Checks
11. Care Support
12. Journal
13. Hope Directory
14. Hope Events
15. Notifications
16. Analytics
17. Users
18. Settings
19. Lookups
20. App Translations
21. Word Blocking
22. Troubleshooting and security
23. Quick reference

Trainings (`/admin/trainings`) is not in the left menu and is not covered here.
