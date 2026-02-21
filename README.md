# 🌪️ Web-based Typhoon Evacuation Mapping Platform
### *Leveraging GIS and Random Forest Algorithm for Identifying Safe Shelter in Virac, Catanduanes*

The **Typhoon Evacuation Mapping Platform** is a specialized web application designed to enhance disaster preparedness and response in **Virac, Catanduanes**. By integrating **Geographic Information Systems (GIS)** and the **Random Forest** machine learning algorithm, the system identifies hazard-prone zones, classifies evacuation shelters based on safety and capacity, and recommends the safest routes for evacuees.

---

## 🚀 Key Features

* **Real-time Hazard Visualization:** Interactive mapping of flood, landslide, liquefaction, and storm surge-prone areas using **Leaflet.js** and **OpenStreetMap**.
* **AI-Powered Shelter Classification:** Uses a **Random Forest model** to predict and recommend safe shelters based on elevation, accessibility, and proximity to hazards.
* **Smart Evacuation Routing:** Automatically generates optimized and safe evacuation routes, including warning triggers if a path intersects with a hazard zone.
* **Offline First Functionality:** Designed to operate during power or internet outages by utilizing **Service Workers** and **IndexedDB** for local data access.
* **Comprehensive Shelter Directory:** Provides detailed information on 53 verified public and private shelters, including capacity, owner details, and real-time availability.

---

## 📊 Technical Performance

The system's predictive model and overall quality have been rigorously evaluated:

* **Model Accuracy:** Achieved **93.1% accuracy** and an **AUC-ROC of 0.963**, indicating excellent capability in distinguishing between safe and unsafe shelters.
* **Software Quality:** Evaluated under the **ISO/IEC 25010** standard, receiving an overall weighted mean of **3.9 (Agree/Satisfactory)** across categories like functional suitability and reliability.

---

## 🛠️ Tech Stack

* **Frontend:** HTML5, CSS3, **Vanilla JavaScript (ES6+)**
* **Mapping:** Leaflet.js, OpenStreetMap API
* **Backend:** PHP (Apache/XAMPP)
* **Database:** MySQL (Relational) & IndexedDB (Offline Storage)
* **Machine Learning:** Scikit-learn (Random Forest implementation)

---

## 💻 Installation & Setup

1.  **Clone the Repository:**
    ```bash
    git clone [https://github.com/MarYann017/Typhoon-Mapping.git](https://github.com/MarYann017/Typhoon-Mapping.git)
    ```

2.  **Environment Setup (XAMPP):**
    * Move the project folder to `C:/xampp/htdocs/`.
    * Start **Apache** and **MySQL** from the XAMPP Control Panel.

3.  **Database Configuration:**
    * Create a database named `typhoon_db` in **phpMyAdmin**.
    * Import the `.sql` file found in the `/database` directory.

4.  **Hardware Requirements:**
    * **Processor:** Intel Core i5 or equivalent (minimum).
    * **RAM:** At least 8 GB for smooth map processing.

5.  **Access the App:**
    * Navigate to `localhost/Typhoon-Mapping` using a **Chrome browser** (recommended for offline features).

---

## 📂 Project Structure

```text
Typhoon-Mapping/
├── api/             # API endpoints for dynamic data retrieval
├── assets/          # CSS, Vanilla JS, and image resources
├── database/        # SQL dump files for system initialization
├── includes/        # PHP helper files and DB configuration
├── index.php        # Application entry point (User Dashboard)
└── sw.js            # Service Worker for offline capabilities
