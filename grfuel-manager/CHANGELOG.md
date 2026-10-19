# Changelog

## 4.5.5
- Διορθώθηκε η εμφάνιση της σελίδας σύνδεσης του WordPress μέσα στο `/grfuel-manager/`. Όταν έκλεινε ο browser, το cookie σύνδεσης του WordPress σβηνόταν, ενώ η συνεδρία του Manager παρέμενε, οπότε το `/grfuel-login/` ανακατεύθυνε στον Manager χωρίς έγκυρη σύνδεση. Η συνεδρία του Manager ισχύει πλέον μόνο μαζί με έγκυρη σύνδεση WordPress του ίδιου χρήστη.
- Χωρίς «Να παραμείνω συνδεδεμένος», το cookie της συνεδρίας Manager λήγει πλέον με το κλείσιμο του browser, όπως και του WordPress.

## 4.5.4
- Το ιστορικό τιμών κρατά πλέον μία τιμή ανά ημέρα και προϊόν και από τον πίνακα προϊόντων, όπως ήδη ίσχυε για την καταχώριση από την κάρτα. Τιμές 0 δεν καταγράφονται.
- Εφάπαξ καθαρισμός του υπάρχοντος ιστορικού κατά την ενημέρωση: ανά ημέρα διατηρείται η τελευταία τιμή και αφαιρούνται οι εγγραφές με τιμή 0.
- Διορθώθηκε η γραμμή «Ημερομηνία / Τιμή ημέρας» στις κάρτες των Τιμών Ημέρας, που ξεχείλιζε και έκοβε το κουμπί αφαίρεσης όταν οι κάρτες είναι στενές.

## 4.5.3
- Διορθώθηκε η απώλεια των Τιμών Ημέρας: η αποθήκευση της οθόνης Ρυθμίσεις έσβηνε όλα τα προϊόντα και τις τρέχουσες τιμές τους και επανέφερε τις ρυθμίσεις γραφήματος στις προεπιλογές, επειδή η φόρμα των Ρυθμίσεων δεν περιέχει αυτά τα πεδία. Πλέον διατηρούνται. Το ιστορικό τιμών δεν είχε επηρεαστεί.
- Κάθε αποθήκευση προϊόντων και κάθε νέα τιμή ημέρας γραφόταν δύο φορές στο ιστορικό τιμών. Πλέον γράφεται μία.

## 4.5.2
- Το όριο των 5 προσπαθειών ανά 15 λεπτά στη σελίδα `/grfuel-login/` μετρά πλέον μόνο αποτυχημένες συνδέσεις και αιτήματα κωδικού OTP. Οι επιτυχημένες συνδέσεις δεν κλειδώνουν πια τον λογαριασμό.
- Μια επιτυχημένη σύνδεση (με ή χωρίς OTP) μηδενίζει τον μετρητή προσπαθειών για το όνομα χρήστη και το email του λογαριασμού.

## 4.5.1
- Διορθώθηκε η αποθήκευση και δημοσίευση φορμών από GRFuel Operator: το αίτημα αποθήκευσης του Form Builder ανακατευθυνόταν στον Πίνακα Ελέγχου πριν αποθηκευτούν οι αλλαγές.
- Ο GRFuel Operator μπορεί πλέον να επιλέγει και να ανεβάζει λογότυπο και υδατογράφημα PDF από τη βιβλιοθήκη πολυμέσων στις Ρυθμίσεις.
- Διορθώθηκε κρίσιμο σφάλμα στη Νέα Παραγγελία όταν επιλέγεται φόρμα που δεν έχει αποθηκευτεί ακόμη από τον Form Builder.
- Η σελίδα `/grfuel-manager/` επιστρέφει πλέον HTTP 200 αντί για 404.

## 4.4.0
- Το email OTP και η ξεχωριστή σελίδα σύνδεσης εφαρμόζονται μόνο στους GRFuel Operators. Οι Administrators συνδέονται από το κανονικό WordPress login.
- Η ξεχωριστή σελίδα σύνδεσης χρησιμοποιεί πλέον τη γραμματοσειρά Geom και έξι ανεξάρτητα, responsive πεδία OTP με υποστήριξη paste, backspace και πλοήγησης από το πληκτρολόγιο.
- Το email OTP επανασχεδιάστηκε με table-based responsive HTML για συματότητα με Gmail και άλλους δημοφιλείς clients, με αποστολέα `GRFuel Manager <no-reply@grfuel.gr>`.
- Προστέθηκε ο ειδικός ρόλος `GRFuel Operator` με πλήρη πρόσβαση σε όλες τις λειτουργίες του GRFuel Manager, χωρίς δικαιώματα διαχείρισης WordPress, plugins ή χρηστών.
- Η δημιουργία, ανάθεση ρόλου, έγκριση και ανάκληση Operator παραμένουν αποκλειστικά στον Administrator.
- Προστέθηκε ξεχωριστή σελίδα σύνδεσης `/grfuel-login/` για Administrators και εγκεκριμένους Operators.
- Προστέθηκε email OTP έξι ψηφίων, με διάρκεια πέντε λεπτών, αποθήκευση μόνο του HMAC, όριο προσπαθειών και browser-bound, HttpOnly session marker.
- Το OTP ενεργοποιείται ρητά από τον Administrator αφού ελεγχθεί η αξιόπιστη αποστολή email, ώστε να μην προκληθεί lockout κατά την αναβάθμιση.
- Η αποθήκευση ρυθμίσεων και τιμών ημέρας χρησιμοποιεί περιορισμένα capabilities και εξειδικευμένους nonce-protected handlers.

## 4.3.22
- Διορθώθηκε η επίλυση προϊόντος στο XLSX όταν η ίδια φόρμα περιέχει dropdown προϊόντος για επιχειρήσεις και single-product calculator για οικιακούς πελάτες. Η υποβληθείσα τιμή έχει πλέον προτεραιότητα έναντι κάθε schema fallback.
- Ο fallback υπολογισμός του συνόλου στο XLSX χρησιμοποιεί το ίδιο επιβεβαιωμένο προϊόν της παραγγελίας.

## 4.3.21
- Αντικαταστάθηκε το προβληματικό regular expression καθαρισμού του filename εξαγωγής με ασφαλές `str_replace`, διορθώνοντας το `Unknown modifier ':'` και τα επακόλουθα `headers already sent` σε XLSX/XML exports.

## 4.3.20
- Αντικαταστάθηκαν οι παρωχημένες `wp_targeted_link_rel()` και `wp_targeted_link_rel_callback()` με εσωτερικό ασφαλή sanitizer για links σε checkbox labels. Δεν εμφανίζονται πλέον deprecated notices σε WordPress 6.7+/7.0.

## 4.3.19
- Διορθώθηκε το διπλό sidebar offset του Form Builder. Η κεφαλίδα και ο editor χρησιμοποιούν πλέον όλο το διαθέσιμο πλάτος, με σωστή ευθυγράμμιση και σε collapsed/mobile κατάσταση.

## 4.3.18
- Διορθώθηκε η μεμονωμένη διαγραφή παραγγελίας ώστε το κουμπί της λίστας να χρησιμοποιεί το απαιτούμενο POST αίτημα με nonce.
- Προστέθηκε σταθερή διάταξη PDF δύο στηλών για οικιακούς πελάτες: στοιχεία πελάτη, ΑΦΜ/μετρητής, επικοινωνία, ποσότητα/σύνολο, παράδοση και συναίνεση GDPR.
- Σε περιβάλλον `WP_DEBUG` οι αποτυχημένες υποβολές εμφανίζουν μη ευαίσθητο validation code για ασφαλή διάγνωση· στο production παραμένει το γενικό μήνυμα.
- Τα διπλά schema fields του ίδιου PDF slot δεν δημιουργούν πλέον πρόσθετη γραμμή· προτιμάται αυτόματα το μη κενό πεδίο.
- Το επάνω Form Builder header ακολουθεί ανεξάρτητα το ίδιο sidebar gutter με τη Νέα Παραγγελία.
- Η ταξινόμηση πεδίων στο εταιρικό PDF χρησιμοποιεί και το αποθηκευμένο schema label, ώστε placeholders να μην αλλάζουν τη θέση της Διεύθυνσης ή άλλων πεδίων.
- Αφαιρέθηκε το διπλό οριζόντιο offset του WordPress CPT editor ώστε ο Form Builder να έχει ακριβώς το πλάτος της Νέας Παραγγελίας.
- Ο Form Builder χρησιμοποιεί πλέον ολόκληρο το διαθέσιμο πλάτος δεξιά από το application sidebar.
- Η εταιρική διάταξη PDF έγινε τέσσερις σειρές των τριών πεδίων και επανήλθαν τα τρία summary boxes.
- Στα PDF επιχειρήσεων το Τηλέφωνο εμφανίζεται ως τρίτο πεδίο στη γραμμή ΑΦΜ/Email/Τηλέφωνο.
- Προστέθηκε ρητή κανονικοποίηση ελληνικών τόνων/διαλυτικών, καθώς το WordPress `remove_accents()` δεν τους αφαιρεί με συνέπεια.
- Διορθώθηκε η Unicode αναγνώριση ελληνικών labels/τιμών για την ταξινόμηση PDF επιχειρήσεων.
- Τα PDF επιχειρήσεων εμφανίζουν πλέον σταθερή διάταξη Τύπου Πελάτη/Επωνυμίας, ΑΦΜ/Email, Προϊόντος/Ποσότητας και Ημερομηνίας/Νομού/Πόλης, χωρίς σύνολο παραγγελίας.
- Ενοποιήθηκε το frontend και server-side fallback σύνδεσης Πόλης με τον μοναδικό Νομό σε παλαιότερες φόρμες.
- Διορθώθηκε η φόρτωση των δεδομένων Νομού/Πόλης σε themes και builders που αποδίδουν τα shortcodes σε μεταγενέστερο στάδιο.
- Προστέθηκε ασφαλής συμβατότητα import για exports έως 4.3.17 των οποίων το legacy checksum δεν αναπαράγεται μετά την αποκωδικοποίηση JSON.
- Ενισχύθηκε η δημόσια υποβολή με όρια μεγέθους/πλήθους πεδίων και κατανάλωση του rate limit μόνο μετά την επιτυχή επικύρωση.
- Το Cloudflare Turnstile αποτυγχάνει πλέον με ασφάλεια και επαληθεύει υποχρεωτικά action και hostname.
- Τα imports και το πλήρες restore κανονικοποιούν και επικυρώνουν φόρμες, submissions, ρυθμίσεις και ιστορικό τιμών πριν την αντικατάσταση δεδομένων.
- Οι μεταβαλλόμενες admin ενέργειες απαιτούν POST, δικαίωμα διαχειριστή και nonce. Διορθώθηκε η μεμονωμένη διαγραφή παραγγελίας.
- Τα backups εξαιρούν μυστικά κλειδιά από προεπιλογή και κατεβαίνουν με private/no-cache headers.
- Προστέθηκαν αυτόματη διατήρηση δεδομένων και διασύνδεση με τα εργαλεία εξαγωγής/διαγραφής προσωπικών δεδομένων του WordPress.
- Αφαιρέθηκε πιθανότητα stored HTML injection στις admin ειδοποιήσεις και προστέθηκαν όρια/cache στα δημόσια γραφήματα.

## 4.3.17
- Προστέθηκε εμφανής ενότητα «Λογική επιλογής» στα radio fields του Form Builder.
- Η «Προσωρινή μη διαθεσιμότητα» μπορεί να υπερισχύει προσωρινά των dependencies για μία επιλεγμένη radio τιμή, χωρίς να τροποποιεί ή να διαγράφει τους υπάρχοντες κανόνες.
- Όταν η ρύθμιση απενεργοποιείται, η κανονική ροή της φόρμας επανέρχεται αυτόματα.

## 4.3.16
- Διευκρινίστηκε και ενισχύθηκε η «Προσωρινή απενεργοποίηση επιλογής» στα radio fields.
- Η επιλεγμένη radio επιλογή μπορεί να υπερισχύει προσωρινά όλων των dependencies, να αποκρύπτει τα υπόλοιπα πεδία και να εμφανίζει μόνο ενημερωτικό μήνυμα.
- Με την απενεργοποίηση της ρύθμισης επανέρχεται αυτόματα η κανονική συμπεριφορά των dependencies, χωρίς απώλεια των κανόνων της φόρμας.

## 4.3.15
- Έγινε εμφανέστερη η ρύθμιση «Περιορισμός επιλογής» μέσα στα radio fields του Form Builder, με σαφείς ετικέτες και ένδειξη κατάστασης.

# 4.3.14
- Προστέθηκε προαιρετική ρύθμιση στα radio fields ώστε μία επιλογή να εμφανίζει μόνο ενημερωτικό μήνυμα και να αποκρύπτει τα υπόλοιπα πεδία και την υποβολή.
- Η ρύθμιση περιλαμβάνει επιλογή της τιμής και επεξεργάσιμο κείμενο μηνύματος, με server-side προστασία από παράκαμψη.

# 4.3.13
- Διορθώθηκαν τα πεδία email και τηλεφώνου αποκλειστικά στη Νέα Παραγγελία με inline admin styling, ώστε να έχουν ίδιο border, radius και ύψος με τα υπόλοιπα πεδία.

# 4.3.12
- Διορθώθηκε οριστικά το border και το border-radius των πεδίων email και τηλεφώνου στη Νέα Παραγγελία, με χρήση των ίδιων theme variables όπως τα υπόλοιπα πεδία.

## 4.3.11
- Διορθώθηκε οριστικά η εμφάνιση των πεδίων email και τηλεφώνου στη Νέα Παραγγελία ώστε να είναι ίδια με όλα τα υπόλοιπα πεδία.

# Changelog

## 4.3.10
- Ενοποιήθηκε η εμφάνιση των πεδίων email και τηλεφώνου στη Νέα Παραγγελία.
- Η ποσότητα, η επιλογή προϊόντος και τα γειτονικά πεδία έχουν κοινό ύψος και στοίχιση.
- Ανασχεδιάστηκε το κουμπί αποθήκευσης ώστε να ταιριάζει με την αισθητική του Manager.

## 4.3.9
- Διορθώθηκε η Νέα Παραγγελία ώστε να ακολουθεί τη δημόσια φόρμα: χωρίς ορατά labels στα πεδία, χωρίς `lt`, με λειτουργικό datepicker και πόλεις ανά επιλεγμένο νομό.

# Changelog

## 4.3.8

- Οι ημερομηνίες στο PDF εμφανίζονται πλέον σε μορφή `dd/mm/yyyy`.
- Τα χρηματικά ποσά στο PDF χρησιμοποιούν τελεία στις χιλιάδες και κόμμα στα δεκαδικά, π.χ. `1.234,56 €`.
- Το εικονίδιο στην επιλογή φόρμας της Νέας Παραγγελίας αντικαταστάθηκε με εικονίδιο clipboard.

# GRFuel Manager Changelog

## 4.3.7
- Επαναφέρθηκαν όλες οι αλλαγές έως την 4.3.6 πάνω σε ασφαλή βάση.
- Τα τελευταία έξι πεδία στο PDF έχουν σταθερή σειρά: Διεύθυνση, Τηλέφωνο, Email, Επιθυμητή ημερομηνία, Ποσότητα, Σύνολο παραγγελίας.
- Διατηρήθηκαν ανέπαφα τα Acrobat PDF icons στη λίστα παραγγελιών.

## 4.3.2
- Αφαιρέθηκε η αποδοχή Πολιτικής Απορρήτου από τα PDF παραγγελιών.
- Το Σύνολο παραγγελίας εμφανίζεται στη θέση της αποδοχής πολιτικής.

## 4.3.1
- Προστέθηκε υδατογράφημα PNG/SVG στο PDF Branding με επιλογέα opacity.
- Το υδατογράφημα κεντράρεται σε κάθε σελίδα PDF με μέγιστο πλάτος 3/4 και ύψος 3/5.
- Αντικαταστάθηκε το εικονίδιο PDF στις παραγγελίες με σύμβολο Acrobat.
- Το εσωτερικό Changelog απέκτησε card layout αντίστοιχο του Stavros Floral Studio Suite.

## 4.3.0
- Προστέθηκε αποθηκευόμενη επιλογή φωτεινού ή σκοτεινού φόντου στην προεπισκόπηση φόρμας.
- Διορθώθηκε η αναγνωσιμότητα λευκών labels και κειμένων πάνω σε σκοτεινά themes.
- Διορθώθηκαν οι αναλογίες του checkbox ενεργοποίησης mobile push στα κινητά.

## 4.2.9
- Admin-only mobile push notifications μέσω OneSignal.
- Σύνδεση συσκευής, test push και αυτόματη ειδοποίηση σε νέα παραγγελία.
- Το API key παραμένει αποκλειστικά στον WordPress server.

## 4.2.7

- Διορθώθηκε το tooltip των γραφημάτων ώστε η αριθμητική τιμή να κάνει ομαλό count-up animation μόλις ο δείκτης περάσει πάνω από κάθε σημείο.
- Το animation επανεκκινεί σε κάθε διαφορετικό σημείο και διατηρεί touch και keyboard υποστήριξη.

## 4.2.6

- Το sticky mobile header βρίσκεται πλέον στο απολύτως επάνω άκρο της οθόνης, ακόμη και όταν εμφανίζεται η WordPress admin bar.
- Το off-canvas sidebar καλύπτει ολόκληρο το ύψος της συσκευής με `100dvh`/`100svh` fallback και υποστήριξη safe-area.

## 4.2.5

- Το tooltip των γραφημάτων χρησιμοποιεί πλέον ομαλή είσοδο και animated μετάβαση της τιμής, σύμφωνα με το reference plugin.
- Το mobile navigation ανασχεδιάστηκε με sticky top bar, burger αριστερά, κεντραρισμένο brand και close button στο off-canvas sidebar.
- Οι κύκλοι του Changelog κεντραρίστηκαν στην κάθετη γραμμή και οι αριθμοί έκδοσης εμφανίζονται ως απλό κείμενο δεξιά από τους κύκλους.

## 4.2.4
- Το off-canvas sidebar στο mobile εμφανίζει εικονίδια και τίτλους, ανεξάρτητα από την αποθηκευμένη κατάσταση σύμπτυξης του desktop.
- Διορθώθηκε το burger button ώστε να παραμένει μέσα στα όρια της οθόνης και να υποστηρίζει safe-area insets.
- Το Changelog έγινε πλήρους πλάτους και βελτιώθηκε αισθητικά.

## 4.2.3
- Διορθώθηκε η εφαρμογή των dependencies και των required πεδίων στη δημιουργία παραγγελίας από admin.
- Προστέθηκε ανεξάρτητο responsive grid για τα πεδία της admin φόρμας.
- Προστέθηκε sticky mobile header με λογότυπο και burger menu.
- Βελτιώθηκε αισθητικά η ανεξάρτητη σελίδα Changelog.

# Changelog

## 4.3.3
- Διορθώθηκε οριστικά η αφαίρεση της αποδοχής Πολιτικής Απορρήτου από τα PDF.
- Το Σύνολο παραγγελίας εμφανίζεται στη θέση της και υπολογίζεται ξανά για παλαιότερες παραγγελίες όταν δεν έχει αποθηκευτεί.

## 4.2.8
- Διορθώθηκε το mobile horizontal overflow ώστε ο manager να μην ξεπερνά το πλάτος της οθόνης.
- Προστέθηκε επίσημη φόρτωση της Geom από Google Fonts και καθολική εφαρμογή της στο manager.

## 4.2.2

- Added a staggered character animation to chart tooltips.
- Moved the changelog out of Documentation into its own sidebar page.

## 4.2.1

- Centered the sidebar footer content with the version and developer credit directly on consecutive lines.
- Linked only the word “Dazzlink” and removed hover backgrounds from the link.
- Centered the logout action and limited its hover effect to a subtle scale up.

## 4.2.0

- Added non-destructive versioned database migrations; updates no longer reset products, daily prices, price history, forms, orders or settings.
- Added a persistent catalog snapshot as an additional safeguard for daily products and price history.
- Added responsive mobile manager navigation with a burger button, off-canvas sidebar and overlay.
- Updated the sidebar footer to one-line version text, Dazzlink credit link and a real WordPress logout action.
- Applied Geom throughout the manager with inheritance fallback.
- Added transparent/inherit and light display modes for public price charts.
- Added admin order creation driven directly by the selected Form Builder configuration, including dependencies and required validation.
- Admin-created orders store source and administrator metadata.

## 4.1.5
- Replaced the WordPress admin-menu icon with the cleaned white GRFuel SVG.
- Replaced the manager sidebar logo with the cleaned full-color GRFuel SVG.
- Removed Illustrator metadata, unused classes and external gradient references from both SVGs.
- No other plugin behavior was changed.

## 4.1.4
- Restored the visual color picker for form appearance settings.
- Added an opacity slider that stores transparent colors as RGBA values.
- No other plugin behavior was changed.

## 4.1.3
- Added RGBA color values with opacity support to form appearance settings.
- Added individual form import from GRFuel Manager form-export JSON files, including checksum validation.
- No other plugin behavior was changed.

## 4.1.3
- Added RGBA color values with opacity support to form appearance settings.
- Added individual form import from GRFuel Manager form-export JSON files, including checksum validation.
- No other plugin behavior was changed.

## 4.1.2

- Αφαιρέθηκε το ανεπιθύμητο οριζόντιο scroll από το sidebar.
- Διατηρείται μόνο η κατακόρυφη κύλιση όταν απαιτείται.

## 4.1.1

- Προστέθηκε κουμπί Export σε κάθε γραμμή φόρμας.
- Το μεμονωμένο export κατεβάζει τοπικό JSON με τα πεδία, τις dependencies, τα responsive πλάτη και τις ρυθμίσεις απάντησης μετά την υποβολή.
- Προστέθηκε SHA-256 checksum στο αρχείο μεμονωμένης φόρμας.

## 4.1.0

- Προστέθηκε νέα ενότητα Backup / Restore στο sidebar.
- Το πλήρες backup περιλαμβάνει ρυθμίσεις, φόρμες και configuration, προϊόντα, ιστορικό τιμών, παραγγελίες και κατάσταση ειδοποιήσεων.
- Τα backup αρχεία κατεβαίνουν τοπικά ως JSON και δεν αποθηκεύονται στον server.
- Η επαναφορά αντικαθιστά με ασφάλεια τα δεδομένα του plugin και διατηρεί τη σύνδεση παραγγελιών με τις φόρμες.
- Προστέθηκε έλεγχος ακεραιότητας SHA-256 πριν από κάθε restore.

## 4.0.4

- Διορθώθηκε η στοίχιση του κουμπιού σύμπτυξης στη συμπτυγμένη προβολή ώστε να βρίσκεται ακριβώς κάτω από τα υπόλοιπα εικονίδια.
- Αφαιρέθηκε το παλιό absolute positioning που το μετακινούσε προς τη δεξιά πλευρά του sidebar.

## 4.0.3
- Διορθώθηκε οριστικά η στοίχιση του εικονιδίου σύμπτυξης με τα υπόλοιπα εικονίδια στη συμπτυγμένη προβολή.
- Το κουμπί σύμπτυξης δεν εμφανίζει background σε hover ή focus.
- Προστέθηκαν οι εκδόσεις 4.0.2 και 4.0.3 στο ορατό changelog της τεκμηρίωσης.

## 4.0.2
- Ευθυγραμμίστηκε το εικονίδιο σύμπτυξης με τα υπόλοιπα εικονίδια όταν το sidebar είναι κλειστό.
- Αφαιρέθηκε το background στο hover και στο focus του κουμπιού σύμπτυξης.

## 4.0.1

- Added a visible Changelog section inside the plugin documentation.
- Removed the active blue background from collapsed sidebar items while keeping the active icon white.
- Redesigned the sidebar collapse control as a borderless navigation row with directional arrow and hover-revealed label.
- Matched the Form Builder Dazzlink header width to the remaining builder panels.

## 4.0.0

- Πρώτη σταθερή έκδοση του GRFuel Manager.
- Επικαιροποιήθηκε η τεκμηρίωση και ενοποιήθηκαν Form Builder, Grid/Breakpoints και Dependencies στην ενότητα «Φόρμες».
- Διορθώθηκε η κλίση του μετρητή στοιχείων στον builder («στοιχείο» / «στοιχεία»).
- Προστέθηκε δεξί περιθώριο στην ένδειξη διαθέσιμων πλατών της διάταξης φόρμας.
- Τα πεδία σε σειρές με διαφορετικό βοηθητικό κείμενο στοιχίζονται πλέον στο επάνω μέρος.
- Ανανεώθηκε το κουμπί σύμπτυξης του sidebar.
- Διορθώθηκε το collapsed sidebar ώστε οι Παραγγελίες να κρατούν το εικονίδιο και να κρύβουν τον τίτλο.

## 4.0.0-alpha.29

- Διορθώθηκε η αρχικοποίηση του panel ειδοποιήσεων browser στη σελίδα ρυθμίσεων.
- Η κατάσταση άδειας ολοκληρώνει πλέον τον έλεγχο αντί να μένει στο «Έλεγχος κατάστασης…».
- Τα κουμπιά «Ενεργοποίηση» και «Δοκιμαστική ειδοποίηση» ανταποκρίνονται κανονικά.

## 4.0.0-alpha.28

- Added a visible browser-notification permission status: active, not enabled, blocked, or unsupported.
- Added a real desktop test-notification button.
- Hide permission controls when they are not applicable and show clear browser-specific guidance.

## 4.0.0-alpha.27
- Προστέθηκε επιλογή πλάτους 2/3 στα πεδία του form builder.
- Το νέο πλάτος αποθηκεύεται, εμφανίζεται σωστά στον builder και αποδίδεται ως responsive UIkit width στο frontend.

## 4.0.0-alpha.26
- Fixed the order badge so a zero count is completely hidden.
- Restored visible desktop and mobile chart shortcode controls in Daily Prices.

## 4.0.0-alpha.23
- Excel: οριστική ενοποίηση Ονοματεπώνυμο/Επωνυμία/Πελάτης σε μία στήλη Πελάτης.
- Excel: αφαιρέθηκε η στήλη Υποβολή.
- Excel: ο τίτλος συναίνεσης κανονικοποιείται πάντα σε Πολιτική Απορρήτου.

## 4.0.0-alpha.22
- Διορθώθηκε ο τίτλος της στήλης συναίνεσης σε «Πολιτική Απορρήτου» ανεξάρτητα από το HTML label της φόρμας.
- Ενοποιήθηκαν οριστικά τα πεδία «Ονοματεπώνυμο» και «Πελάτης» στη μία στήλη «Πελάτης».
- Αφαιρέθηκε η δεύτερη στήλη ημερομηνίας της φόρμας από το XLSX, καθώς υπάρχει ήδη η ημερομηνία υποβολής.
- Διορθώθηκε η ανάκτηση του επιλεγμένου προϊόντος από το product field του price calculator και από σταθερά προϊόντα οικιακών πελατών.

## 4.0.0-alpha.20
- Βελτιώθηκε το Excel export: νέο όνομα αρχείου, ενοποίηση ποσότητας, στήλη τιμής παραγγελίας, αφαίρεση security verification, σωστή ονομασία Πολιτικής Απορρήτου και διορθωμένος χρωματισμός/περίγραμμα γραμμών.

## 4.0.0-alpha.18

## 4.0.0-alpha.20
- Improved XLSX column widths so Greek headings are fully visible.
- Removed AutoFilter from exported workbooks.
- Replaced the HTML privacy-policy checkbox label with a clean “Πολιτική Απορρήτου” column heading.
- Strengthened row borders and the visual distinction between domestic and business customers.


- Redesigned order XLSX export to follow form-field order.
- Consolidated personal/company names into **Πελάτης**.
- Consolidated product choices into **Προϊόν παραγγελίας**.
- Added borders, AutoFilter, frozen header, customer-type row shading, and em dashes for empty values.

## 4.0.0-alpha.17
- Replaced the mobile price list with a true paged line chart optimized for narrow screens.
- Displays eight days per view with previous/next navigation and tap tooltips.

## 4.0.0-alpha.17
- Added a dedicated mobile-only price history shortcode: `[grfuel_price_chart_mobile]`.
- Added a vertical, touch-friendly price timeline that remains readable with long histories and does not require horizontal scrolling.
- Added the mobile shortcode to each product’s shortcode list in Daily Prices.

## 4.0.0-alpha.15
- Πλήρης ελληνικοποίηση της ενότητας Παραγγελίες και του πλαισίου εξαγωγής.
- Αφαιρέθηκε η περιττή ετικέτα «Form» πάνω από την επιλογή φόρμας στην εξαγωγή.

# 4.0.0-alpha.12

- Άλλαξε η mobile κλίμακα του γραφήματος: ο SVG καμβάς δεν συμπιέζεται πλέον στα 620px, αλλά ξεκινά από 900px και αυξάνεται ανάλογα με τον αριθμό των σημείων.
- Διατηρήθηκε το οριζόντιο touch scroll, με μεγαλύτερα bullets και πιο ευανάγνωστες ετικέτες αξόνων στις μικρές οθόνες.

# 4.0.0-alpha.11

- YOOtheme-safe animation initialization for chart lines and points, including AJAX/dynamic DOM insertion.
- Smaller axis labels and improved summary metric spacing.
- Responsive chart viewport with horizontal scrolling instead of unreadable downscaling.

# 4.0.0-alpha.10
- Νέο αναβαθμισμένο γράφημα τιμών με πλέγμα, σημεία δεδομένων, άξονες ημερομηνιών και συνοπτικά στατιστικά.
- Μόνιμο αρχείο ιστορικών τιμών στο διαχειριστικό.
- Διαγραφή μεμονωμένης ιστορικής τιμής από τον διαχειριστή.

# 4.0.0-alpha.9

- Διορθώθηκε η οριζόντια στοίχιση και το πλάτος της σελίδας επεξεργασίας φόρμας, ώστε να διατηρεί την ίδια απόσταση από το sidebar και τη δεξιά πλευρά με τις υπόλοιπες σελίδες.

# 4.0.0-alpha.8

- Η ενότητα «Φόρμες» παραμένει ενεργή κατά την επεξεργασία στον Builder.
- Προστέθηκε ασφαλής διαγραφή φόρμας από τη λίστα διαχείρισης.
- Οι Τιμές Ημέρας υποστηρίζουν ορατές, δυναμικές σειρές ημερομηνίας και τιμής και μαζική αποθήκευση.
- Ενοποιήθηκε το πλάτος της επεξεργασίας φόρμας με τις υπόλοιπες σελίδες διαχείρισης.

# 4.0.0-alpha.7

- Νέα φόρμα μέσω popup στη Διαχείριση φορμών και αφαίρεση της ξεχωριστής επιλογής από το sidebar.
- Διόρθωση αποθήκευσης/επιστροφής στη λίστα φορμών και επαναφορά λειτουργίας του builder.
- Καθολικό πλάτος περιεχομένου, τίτλος Παραγγελίες και επισκευή διάταξης/προσθήκης στις Τιμές Ημέρας.
- Διόρθωση JavaScript identifiers που είχαν μεταφραστεί κατά λάθος.

# Changelog

## 4.0.0-alpha.1
- Added fullscreen GRFuel application shell.
- Added standalone operations dashboard with order metrics and recent orders.
- Added dedicated sidebar navigation and removed WordPress admin chrome on GRFuel screens.
- Preserved existing forms, submissions, pricing, exports and notifications.

## 3.0.0-alpha.3
- Replaced the oven-timer melody with a short two-strike bell chime using layered harmonic partials.
- Bumped asset version to invalidate cached notification JavaScript.

## 3.0.0-alpha.2
- Fixed residential customer names and Single Product Calculator product summaries.
- Replaced the order beep with a warm oven-timer chime.

## 3.0.0-alpha.1

- Added a configurable Single Product Calculator placeholder shown before a quantity is entered.
- Default placeholder: «Αυτόματος υπολογισμός κόστους παραγγελίας».
- Normalized the frontend delivery-date field height, padding, line-height and box sizing to match other inputs.

## 2.0.0-alpha.7

- Fixed Single Product Calculator live calculation when typing litres.
- Calculator field references now follow sanitized/de-duplicated field keys.
- Existing forms with one Number field recover automatically if the stored quantity reference is missing or stale.
- Added robust frontend input lookup and delegated input/change fallback.

## 2.0.0-alpha.6

- Replaced calculator pricing textareas with compact dynamic pricing rows.
- Both calculators now use Product, Quantity and Price columns.
- Added Add row and Remove row controls.
- Quantity 0 represents the base price; positive quantities create pricing tiers.
- Existing pricing data remains compatible and is converted automatically in the editor.

## 2.0.0-alpha.5

- Added Single Product Calculator for B2C flows without a product dropdown.
- Supports fixed product label, quantity source, base price, unlimited quantity tiers, dependencies and hidden label.
- Existing dropdown/radio Product Price Calculator remains unchanged.

## 2.0.0-alpha.4

- Kept the Number / Quantity unit suffix inside the input, aligned to the right of the entered number.
- Hardened frontend and builder styles against YOOtheme/UIkit overrides.

## 2.0.0-alpha.3
- Added checkbox submission values for concise GDPR consent records.
- Added safe anchor support in checkbox labels.


## 2.0.0-alpha.1

- Rebranded the plugin as **GRFuel Manager**.
- Added the tagline **Forms that become workflows.**
- Preserved all 1.x forms, settings and submissions.
- Added `[beyond_form]` and `[beyond_price]` shortcode aliases.
- Kept legacy shortcodes for backward compatibility.
- Updated XLSX headers to `Α/Α` and `Υποβολή`.
- Removed the XLSX header autofilter.
- Updated PDF and workbook product metadata.
- Added architecture and migration notes.

## 2.0.0-alpha.3
- Added editable visual suffix for Number / Quantity fields, defaulting to `lt`.
- Quantity values remain numeric in submissions and exports.
- Price Calculator continues to format totals with a spaced currency symbol, e.g. `545,00 €`.

## 4.0.0-alpha.2
- Fixed the Forms list content offset in fullscreen application mode.
- Added collapsible icon-only sidebar with saved administrator preference.
- Added dedicated Daily Prices sidebar page.
- Added persistent price history and rise/fall indicators.
- Added `[grfuel_price_chart]` shortcode for responsive YOOtheme price-history charts.

## 4.0.0-alpha.5
- Added persistent native desktop notifications with order details and in-app GRFuel notification cards.
- Added unread-order badge to the Παραγγελίες sidebar item.
- Added title attention animation while new orders remain unread.
- Added manual dated price-history entries per product.
- Added chart generation settings for default period, maximum visitor period and frontend controls.
- Added interactive frontend chart period selection through AJAX.
- Improved the legacy new/edit form editor layout inside the fullscreen application shell.
- Added `[grfuel_form]` as the primary public form shortcode.

## 4.0.0-alpha.6
- Μετονομάστηκε το Dashboard σε «Πίνακας Ελέγχου» και ενοποιήθηκε η ελληνική ορολογία στις Παραγγελίες και στον σχεδιαστή φορμών.
- Η σελίδα Νέα φόρμα χρησιμοποιεί πλέον όλο το διαθέσιμο πλάτος, χωρίς το δεξί WordPress sidebar, και διαθέτει κουμπί «Αποθήκευση φόρμας» στο κάτω μέρος.
- Βελτιώθηκε το πεδίο ονόματος φόρμας και το placeholder αποκρύπτεται μόλις το πεδίο λάβει focus.
- Η διαχείριση προϊόντων μεταφέρθηκε αποκλειστικά στις «Τιμές Ημέρας», με δυναμική προσθήκη γραμμών χωρίς reload, ιστορικές ημερομηνίες και ακρίβεια τριών δεκαδικών.
- Οι ρυθμίσεις εμφάνισης Labels, Placeholders, Fields και Submit ενοποιήθηκαν στο μενού «Φόρμες».
- Προστέθηκε επιλογή τεσσάρων ήχων ειδοποίησης με άμεση ακρόαση.

## 4.0.0-alpha.13
- Προστέθηκε tap/click tooltip στα bullets του γραφήματος για κινητά και tablets, με ημερομηνία και τιμή.
- Προστέθηκε υποστήριξη πληκτρολογίου με Enter/Space και κλείσιμο με Escape ή πάτημα εκτός γραφήματος.
- Το ενεργό bullet επισημαίνεται και το tooltip κλείνει αυτόματα κατά την οριζόντια κύλιση.
# 4.5.0

- Added a live HH:MM:SS session countdown to the floating refresh control.
- Fixed the Manager portal returning to login immediately after successful OTP verification.
- Added `/grfuel-manager/` as the dedicated Manager entry point for Operators and Administrators.
- Separated Manager OTP sessions from normal WordPress administrator sessions.
- Added an administrator-configurable Manager session duration (15 minutes to 7 days).
- Added a fixed session refresh control that restarts the configured duration.
- Manager logout now leaves the normal WordPress administrator session untouched.
