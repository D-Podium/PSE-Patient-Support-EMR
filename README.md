**A lightweight PHP-based medical dashboard integrating the Dorra EMR API. Features include patient management, AI-assisted clinical search, appointment retrieval using EMR Patient IDs, and an intuitive encounter creation workflow. Built with a clean, user-friendly interface optimized for doctors in real clinical settings.**

---

## **PSE – Dorra EMR Integrated Medical Dashboard**

PSE is a **modern PHP-based Electronic Medical Record (EMR) dashboard** built for healthcare providers and clinics participating in the Ahead Africa Hackathon.

This system integrates directly with the **Dorra EMR API**, enabling doctors to perform essential clinical operations from a simple, intuitive interface.

### ✔ **Key Features**

#### **🔹 Patient Management**

* Local database stores basic patient information.
* Automatically maps **local patient_id → EMR patient_id**.
* All API calls use valid **EMR IDs** for accuracy and compatibility.

#### **🔹 Appointment Viewer**

* Doctors can select a patient to instantly view appointments fetched from Dorra EMR.
* Shows date, time, status, and notes.
* Displays a helpful message when no patient is selected.

#### **🔹 Encounter Creation**

* A clean, user-friendly modal for creating encounters.
* Optional encounter input fields.
* No technical terms like *JSON Array* exposed to users.
* Successful encounters submit directly to the Dorra API.

#### **🔹 AI Health Assistant**

* Integrated AI search assistant for clinical support.
* Pressing *Enter* also sends the prompt.
* Each response fades away automatically after 40 seconds.
* If no valid answer is returned, message appears in white: **“No AI suggestion returned.”**

#### **🔹 Mobile-Friendly UI**

* Designed based on a custom card-style layout.
* Light, clean, and fast interface tailored for doctors.

### ✔ **Tech Stack**

* **PHP 8+**
* **MySQL**
* **Bootstrap 5**
* **JavaScript / Fetch API**
* **Dorra EMR REST API**
* **Session-based authentication**
