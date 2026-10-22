=== GRFuel Manager ===
Contributors: Dazzlink
Tags: forms, yootheme, uikit, turnstile, excel, submissions
Requires at least: 6.4
Requires PHP: 8.0
Stable tag: 4.5.7
License: GPLv2 or later

Ασφαλής visual form builder για WordPress και YOOtheme/UIkit, με αποθήκευση υποβολών, Cloudflare Turnstile και εξαγωγές XLSX/XML.

== Περιγραφή ==
Το GRFuel Manager — Forms that become workflows. — επιτρέπει τη δημιουργία φορμών με drag & drop, responsive UIkit grid, dependencies μεταξύ πεδίων και ασφαλή καταγραφή των υποβολών.

== Εγκατάσταση ==
1. Ανέβασε το ZIP από Πρόσθετα → Προσθήκη νέου → Μεταφόρτωση προσθέτου.
2. Ενεργοποίησε το GRFuel Manager.
3. Δημιούργησε φόρμα από GRFuel Manager → Φόρμες → Νέα φόρμα.
4. Πρόσθεσε Turnstile keys στις Ρυθμίσεις.

== Χρήστες Manager και OTP ==
1. Ο Administrator δημιουργεί τον χρήστη και επιλέγει ρόλο «GRFuel Operator».
2. Από GRFuel Manager → Χρήστες Manager πατά «Έγκριση». Ο ρόλος από μόνος του δεν επιτρέπει σύνδεση.
3. Επιβεβαίωσε πρώτα ότι το WordPress αποστέλλει email μέσω SMTP και μετά ενεργοποίησε το Email OTP από την ίδια σελίδα.
4. Οι εγκεκριμένοι Operators συνδέονται από `/grfuel-login/` με password και εξαψήφιο email OTP. Ο Administrator συνδέεται κανονικά από τη σελίδα σύνδεσης του WordPress.
5. Ο Operator έχει πλήρη πρόσβαση στο GRFuel Manager, αλλά δεν μπορεί να διαχειριστεί λογαριασμούς, plugins ή το υπόλοιπο WordPress admin.

Για έκτακτη ανάκτηση όταν το email δεν λειτουργεί, ο Administrator μπορεί προσωρινά να ορίσει `define( 'GRFUEL_DISABLE_OTP', true );` στο `wp-config.php`. Η έγκριση Operator συνεχίζει να ελέγχεται.
5. Τοποθέτησε το shortcode στη σελίδα YOOtheme.

== Χρήση ==
Σύρε ένα πεδίο από την παλέτα στο canvas. Πάτησε την κάρτα του για label, placeholder, required, width, breakpoint και dependency. Πρόσθεσε Submit και προαιρετικά Turnstile.

== Εξαγωγές ==
Οι υποβολές εξάγονται σε πραγματικό Excel workbook .xlsx ή XML, με φίλτρα ημερομηνιών, φόρμας και IDs.

== Ασφάλεια ==
Περιλαμβάνονται honeypot, Turnstile server verification, nonces, capability checks, sanitization, escaping, prepared SQL, rate limiting και προστασία formula injection.

== GDPR & διατήρηση δεδομένων ==
Από την οθόνη Υποβολές & Export μπορούμε να διαγράψουμε οριστικά όλες τις αποθηκευμένες υποβολές. Η επαναφορά του submission ID στο 1 επιτρέπεται μόνο όταν ο πίνακας είναι άδειος. Πριν από μαζική διαγραφή συνιστάται export ή άλλο αντίγραφο ασφαλείας, ανάλογα με την πολιτική διατήρησης δεδομένων.

== Changelog ==

= 4.5.7 =
* Σύνδεση του Single product calculator με τις Τιμές Ημέρας, ώστε η τιμή του να ενημερώνεται αυτόματα.

= 4.5.6 =
* Οι σελίδες σύνδεσης και Manager ανοίγουν πάντα σε https όταν το admin είναι σε https.

= 4.5.5 =
* Το /grfuel-login/ δεν ανακατευθύνει πλέον στη σελίδα σύνδεσης του WordPress μέσα στον Manager μετά το κλείσιμο του browser.

= 4.5.4 =
* Μία τιμή ανά ημέρα στο ιστορικό τιμών και καθαρισμός των διπλών εγγραφών και των τιμών 0.
* Διορθώθηκε η εμφάνιση της γραμμής καταχώρισης τιμής στις κάρτες.

= 4.5.3 =
* Η αποθήκευση των Ρυθμίσεων δεν σβήνει πλέον τα προϊόντα και τις Τιμές Ημέρας.
* Το ιστορικό τιμών δεν γράφει πλέον διπλές εγγραφές.

= 4.5.2 =
* Το όριο προσπαθειών σύνδεσης μετρά μόνο αποτυχίες και μηδενίζεται μετά από επιτυχημένη σύνδεση.

= 4.5.1 =
* Ο GRFuel Operator αποθηκεύει και δημοσιεύει φόρμες από τον Form Builder.
* Ο GRFuel Operator επιλέγει λογότυπο και υδατογράφημα PDF από τη βιβλιοθήκη πολυμέσων.
* Διορθώθηκε κρίσιμο σφάλμα στη Νέα Παραγγελία με φόρμα χωρίς αποθηκευμένα πεδία.
* Η σελίδα /grfuel-manager/ επιστρέφει HTTP 200.

= 4.5.0 =
* Το floating κουμπί συνεδρίας εμφανίζει ζωντανή αντίστροφη μέτρηση και επιστρέφει στην πλήρη διάρκεια μετά την ανανέωση.
* Διορθώθηκε η επιστροφή στη σελίδα σύνδεσης αμέσως μετά από επιτυχημένο OTP.
* Νέο ανεξάρτητο URL `/grfuel-manager/` για Operators και Administrators, χωρίς `wp-admin` στη γραμμή διεύθυνσης.
* Ξεχωριστή συνεδρία Manager ώστε το OTP login να μην επηρεάζει το κανονικό WordPress admin login.
* Ρυθμιζόμενη διάρκεια συνεδρίας Manager από τις Ρυθμίσεις (15 λεπτά έως 7 ημέρες).
* Floating κουμπί ανανέωσης κάτω δεξιά που επαναφέρει τη διάρκεια της ενεργής συνεδρίας.
* Ξεχωριστή έξοδος Manager που δεν αποσυνδέει τον Administrator από το WordPress.

= 4.3.22 =
* Η εξαγωγή Excel χρησιμοποιεί πλέον ως απόλυτη προτεραιότητα το προϊόν που αποθηκεύτηκε στην παραγγελία και όχι το fallback ενός άλλου calculator της ίδιας φόρμας.

= 4.3.21 =
* Διορθώθηκε η παραγωγή του ονόματος αρχείου εξαγωγής σε ορισμένες εκδόσεις PHP/PCRE, ώστε τα XLSX και XML να κατεβαίνουν χωρίς warnings ή αλλοίωση των headers.

= 4.3.20 =
* Αφαιρέθηκε η χρήση των παρωχημένων `wp_targeted_link_rel()` helpers για πλήρη συμβατότητα με WordPress 6.7 και νεότερο, διατηρώντας ασφαλή `noopener noreferrer` στα links των checkbox labels.

= 4.3.19 =
* Διορθώθηκε το πλάτος του Form Builder ώστε να χρησιμοποιεί μία φορά το sidebar gutter και να ευθυγραμμίζεται με τις υπόλοιπες σελίδες, τόσο σε ανοικτό όσο και σε collapsed sidebar.

= 4.3.18 =
* Aligned frontend and server-side delivery city validation for legacy forms with one region selector.
* Fixed delivery region/city runtime data loading in themes and builders that render shortcodes at a later stage.
* Added safe import compatibility for legacy exports through 4.3.17 whose checksum cannot be reproduced after JSON decoding.
* Security hardening: validation now runs before public rate-limit consumption and request size/cardinality limits were added.
* Turnstile verification now fails closed and validates both the expected action and site hostname.
* Imported forms, submissions, settings and price history are normalized before restore; imported standalone forms remain drafts.
* State-changing admin actions require POST, capability checks and nonces, including individual submission deletion.
* Backups exclude secrets by default and downloads receive private/no-cache response headers.
* Added configurable automatic data retention and WordPress privacy export/erase integration.
* Hardened admin notifications against stored HTML injection and limited/cached public chart responses.

= 4.2.8 =
* Fixed mobile horizontal overflow.
* Loaded and applied Geom from Google Fonts throughout the manager.


== 4.0.0 ==
* Πρώτη σταθερή έκδοση του GRFuel Manager.
* Οπτικός form builder με responsive πλάτη 1/1, 1/2, 1/3 και 2/3, dependencies και πεδία τόπου παράδοσης.
* Διαχείριση ημερήσιων τιμών, ιστορικό και desktop/mobile γραφήματα.
* Παραγγελίες, PDF/XLSX/XML exports και browser notifications.
* Βελτιωμένη τεκμηρίωση και διορθώσεις στο sidebar και στη στοίχιση του admin UI.

== 4.0.0-alpha.18 ==
* Ανασχεδιασμένο Excel export παραγγελιών με σειρά στηλών βάσει φόρμας.
* Ενοποίηση ονοματεπωνύμου/επωνυμίας στη στήλη «Πελάτης».
* Ενοποίηση επιλογών προϊόντος στη στήλη «Προϊόν παραγγελίας».
* Περίγραμμα, φίλτρα, παγωμένη κεφαλίδα, διακριτικός χρωματισμός τύπου πελάτη και παύλα στα κενά πεδία.

== 3.0.0-alpha.3 ==
* Αντικαταστάθηκε η μελωδία timer με διπλό μεταλλικό chime μικρής καμπάνας.
* Νέα έκδοση assets για παράκαμψη browser και WordPress cache.

== 3.0.0-alpha.2 ==
* Fixed residential customer and fixed-product summaries in Submissions/PDF.
* Added a warm oven-timer notification melody.

= 1.13.0 =
* Βελτιωμένο PDF export: απόκρυψη κενών πεδίων, ημερομηνία στο header, μεγαλύτεροι τίτλοι, σταθερό footer και εκτυπώσιμο gradient.


= 1.12.6 =
* Προστέθηκε ασφαλής μαζική διαγραφή όλων των submissions.
* Προστέθηκε επαναφορά AUTO_INCREMENT ώστε η επόμενη υποβολή να ξεκινά από ID 1, μόνο όταν ο πίνακας είναι άδειος.
* Ενημερώθηκε η ελληνική τεκμηρίωση για GDPR και διατήρηση δεδομένων.

= 1.10.8 =
* Το Date field διαθέτει πλέον αξιόπιστη επιλογή απόκρυψης label και required αστερίσκο μέσα στο placeholder.
* Διορθώθηκε το preview και η συμβατότητα παλαιότερων Date fields.

= 1.10.7 =
* Το required σύμβολο του Date εμφανίζεται αξιόπιστα μέσα στο placeholder όταν το label είναι κρυφό.
* Αντικαταστάθηκε το CSS calendar icon ώστε να αφαιρεθεί η ανεπιθύμητη μικρή παύλα.

= 1.10.6 =
* Προστέθηκε placeholder στο Date field με προεπιλεγμένο κείμενο «Επιθυμητή ημερομηνία παράδοσης».
* Το Date field υποστηρίζει πλέον απόκρυψη label ώστε το κείμενο να εμφανίζεται μέσα στο πεδίο.
* Αφαιρέθηκαν οι custom cursors από το διαχειριστικό του plugin.

= 1.10.5 =
* Προστέθηκε πεδίο Date με native date picker, dependencies, responsive width και αυστηρό server-side validation μορφής YYYY-MM-DD.

= 1.10.2 =
* Διορθώθηκε η εφαρμογή του χρώματος περιγράμματος στα πεδία σε YOOtheme/UIkit.
* Προστέθηκε σαφές focus border που δεν υπερκαλύπτεται από το theme.

= 1.10.1 =
* Fixed the Settings sidebar tabs not opening because the admin JavaScript was not loaded on the Settings screen.


= 1.9.0 =
* Added centralized appearance settings for labels, placeholders, fields and submit buttons.
* Added configurable colors, font families, sizes, weights, field height and border radius.

= 1.7.0 =
* Compact Dazzlink admin interface.
* Author metadata Dazzlink.
* Νέα ενσωματωμένη τεκμηρίωση στα ελληνικά.

= 1.10.9 =
* Διορθώθηκε η εμφάνιση και λειτουργία των επιλογών Required και Απόκρυψη label στον form builder.


== PDF Branding ==
Στις Ρυθμίσεις → PDF Branding ορίζουμε επωνυμία, λογότυπο PNG/SVG και χρωματική παλέτα για τα PDF exports. Οι τίτλοι των απαντήσεων χρησιμοποιούν πρώτα το placeholder του πεδίου και έπειτα το label ή το field name.


== GRFuel Manager 2.0 Alpha ==

Η πρώτη alpha έκδοση εισάγει το νέο branding και τη βάση μετάβασης προς workflow-first αρχιτεκτονική. Διατηρεί τα υπάρχοντα δεδομένα και προσθέτει τα shortcode aliases:

* `[beyond_form id="123"]`
* `[beyond_price form="123" product="Προϊόν"]`

Τα παλαιά shortcodes παραμένουν ενεργά για backward compatibility.


== 2.0.0-alpha.4 ==
* Προστέθηκε ξεχωριστή τιμή υποβολής για checkbox/GDPR consent.
* Επιτρέπονται ασφαλείς σύνδεσμοι προς την Πολιτική Απορρήτου στο ορατό κείμενο checkbox.

== 2.0.0-alpha.6 ==
* Προστέθηκε Single Product Calculator για σταθερό προϊόν χωρίς Dropdown/Radio.
* Υποστηρίζει βασική τιμή, κλιμακωτές τιμές, quantity field, dependencies και hide label.
* Το υπάρχον Product Price Calculator παραμένει ανέπαφο.

== 3.0.0-alpha.1 ==
* Adds a configurable empty-state message to the Single Product Calculator.
* Aligns the delivery date field height exactly with the other frontend inputs.

== 4.0.0-alpha.9 ==
* Fixed the form editor width and sidebar gutter so it follows the universal admin layout.

== 4.0.0-alpha.8 ==
* Active sidebar state for Builder, form deletion, multi-row daily prices and universal editor width.

== 2.0.0-alpha.7 ==
* Fixed Single Product Calculator quantity-field detection and live recalculation.
* Preserved calculator field references when field keys are sanitized.
* Added safe fallback to the only Number field for older alpha forms.

== Ενημέρωση 4.0.0-alpha.6 ==
Η διαχείριση προϊόντων και ιστορικών τιμών γίνεται πλέον αποκλειστικά από την ενότητα «Τιμές Ημέρας». Επιτρέπονται παλαιότερες ημερομηνίες για δοκιμές γραφήματος και οι τιμές αποθηκεύονται με τρία δεκαδικά. Η δημιουργία φόρμας είναι πλήρους πλάτους και αποθηκεύεται από το κουμπί στο κάτω μέρος. Οι ρυθμίσεις εμφάνισης έχουν ενοποιηθεί στην κατηγορία «Φόρμες», ενώ στις ειδοποιήσεις υπάρχει επιλογή και ακρόαση ήχου.

== 4.0.0-alpha.10 ==
* Ανανεωμένο γράφημα και μόνιμο αρχείο τιμών με δυνατότητα διαγραφής ανά εγγραφή.

== 4.0.0-alpha.12 ==
* Βελτιωμένη κλίμακα του γραφήματος σε κινητά ώστε οι τιμές, οι ημερομηνίες και τα σημεία να παραμένουν ευανάγνωστα.
* Το πλάτος του κινητού καμβά προσαρμόζεται στον αριθμό των καταχωρίσεων και χρησιμοποιεί οριζόντια κύλιση χωρίς συμπίεση.
* Ελαφρώς μεγαλύτερα σημεία και ετικέτες αξόνων μόνο σε μικρές οθόνες.

== 4.0.0-alpha.11 ==
* Διορθώθηκε το animation των σημείων και της γραμμής σε YOOtheme και δυναμικά φορτωμένο περιεχόμενο.
* Μειώθηκε το μέγεθος των τιμών και ημερομηνιών στους άξονες.
* Βελτιώθηκαν τα κενά και η στοίχιση στα στατιστικά του γραφήματος.
* Σε μικρές οθόνες το γράφημα διατηρεί αναγνώσιμο μέγεθος και μετακινείται οριζόντια αντί να συρρικνώνεται.

== 4.0.0-alpha.17 ==
* Νέο shortcode για κινητά: [grfuel_price_chart_mobile].
* Κάθετη απεικόνιση ιστορικού τιμών χωρίς οριζόντια κύλιση, κατάλληλη για πολλές ημερομηνίες.


= 4.3.9 =
* Η Νέα Παραγγελία χρησιμοποιεί πλέον τα ίδια κρυφά labels/placeholders με τη δημόσια φόρμα.
* Αφαιρέθηκε το lt από την ποσότητα στο manager.
* Διορθώθηκε η φόρτωση του datepicker και των εξαρτώμενων πόλεων ανά νομό.
