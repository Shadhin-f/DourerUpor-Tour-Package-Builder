# DourerUpor-Tour-Package-Builder

## Overview
DourerUpor is a tour package builder platform designed to help users explore, build, and share travel packages. The project incorporates multiple design patterns to ensure scalability, maintainability, and efficiency. It also supports multiple login systems (Normal, Google, and GitHub) for user convenience. The platform is built with PHP, leveraging modern design principles and efficient database interactions.

---

## Group Members
- **Uma Banik** (2233679042)
- **Kazi Abdullah Al Hasnaine** (2211688642)
- **Md. Asaduzzaman Sunny** (2211702642)
- **Tahshan Jamil Shadhin** (2231571642)

---

## Features
1. **Multiple Login Systems**: Users can log in using Normal Login, Google OAuth, or GitHub OAuth.
2. **Tour Package Builder**: Users can create and customize travel packages in basic or full modes.
3. **Lazy Loading**: Efficient image loading for better performance.
4. **State Management**: Packages go through states like `Pending`, `Approved`, and `Rejected`.
5. **Observer Notifications**: Followers are notified of package updates.
6. **Dynamic Page Rendering**: Modular page components using the Decorator Pattern.
7. **Review Iterator**: Efficiently iterates through user reviews for packages.

---

## Design Patterns Used
This project implements **8 design patterns** to ensure a robust and scalable architecture:

### 1. **Strategy Pattern**
- **Purpose**: To handle multiple login systems (Normal, Google, GitHub).
- **Implementation**: 
  - `LoginStrategy` interface defines a common contract for all login methods.
  - Concrete strategies (`NormalLogin`, `GoogleLogin`, `GitHubLogin`) implement the interface.
  - `LoginContext` dynamically selects and executes the appropriate login strategy.
- **Efficiency**: Allows easy addition of new login methods without modifying existing code.

### 2. **Singleton Pattern**
- **Purpose**: To ensure a single instance of the database connection.
- **Implementation**: 
  - `Database` class uses a private constructor and a static `getInstance` method.
- **Efficiency**: Reduces overhead by reusing the same database connection across the application.

### 3. **Builder Pattern**
- **Purpose**: To construct complex tour packages step-by-step.
- **Implementation**: 
  - `PackageBuilder` is an abstract class defining the steps to build a package.
  - `TourPackageBuilder` is a concrete implementation for building packages.
  - `PackageDirector` orchestrates the building process.
- **Efficiency**: Separates the construction logic from the representation, making it easier to create and modify packages.

### 4. **Observer Pattern**
- **Purpose**: To notify followers of package updates.
- **Implementation**: 
  - `PackageSubject` manages a list of observers (followers).
  - `UserObserver` receives notifications when a package is updated.
- **Efficiency**: Ensures real-time updates to followers without tightly coupling the components.

### 5. **State Pattern**
- **Purpose**: To manage the lifecycle of a package (`Pending`, `Approved`, `Rejected`).
- **Implementation**: 
  - `PackageState` interface defines state-specific behavior.
  - Concrete states (`PendingState`, `ApprovedState`, `RejectedState`) implement the interface.
  - `Package` class acts as the context, delegating state-specific behavior to the current state.
- **Efficiency**: Simplifies state transitions and ensures consistent behavior.

### 6. **Decorator Pattern**
- **Purpose**: To dynamically add features to pages (e.g., footer, popular section, explore section).
- **Implementation**: 
  - `PageComponent` interface defines the base structure.
  - `BasePage` is the core component.
  - Decorators like `FooterDecorator`, `PopularSection`, and `ExploreSection` add functionality.
- **Efficiency**: Promotes modularity and reusability of page components.

### 7. **Proxy Pattern**
- **Purpose**: To implement lazy loading for images.
- **Implementation**: 
  - `ProxyImage` and `ProxyBackgroundImage` act as placeholders for real images.
  - Real images are loaded only when needed.
- **Efficiency**: Reduces initial load time and improves performance, especially for image-heavy pages.

### 8. **Iterator Pattern**
- **Purpose**: To iterate through user reviews efficiently.
- **Implementation**: 
  - A custom `ReviewIterator` class is used to traverse reviews for a package.
  - The iterator provides a clean and consistent way to access reviews without exposing the underlying data structure.
- **Efficiency**: Simplifies review traversal and ensures compatibility with different data sources.

---

## How the Project Works
1. **User Authentication**:
   - Users can log in using Normal Login, Google OAuth, or GitHub OAuth.
   - The `LoginContext` dynamically selects the appropriate login strategy.

2. **Package Creation**:
   - Users can create packages in basic or full modes using the Builder Pattern.
   - Full mode allows adding destinations, transport details, and savings.

3. **State Management**:
   - Packages start in the `Pending` state.
   - Admins can approve or reject packages, transitioning them to `Approved` or `Rejected` states.

4. **Notifications**:
   - Followers are notified of package updates using the Observer Pattern.

5. **Dynamic Page Rendering**:
   - Pages are built dynamically using the Decorator Pattern, allowing modular addition of sections like Popular and Explore.

6. **Lazy Loading**:
   - Images are loaded only when they come into the viewport, improving performance.

7. **Review Traversal**:
   - The Iterator Pattern is used to traverse reviews for a package, ensuring clean and efficient access.

---

## Efficiency of Design Patterns
- **Scalability**: The use of design patterns ensures the system can handle new features and changes with minimal effort.
- **Performance**: Lazy loading and Singleton Pattern reduce resource usage and improve response times.
- **Maintainability**: Modular design (e.g., Strategy, Decorator) makes the codebase easier to understand and extend.

---

## Conclusion
DourerUpor-Tour-Package-Builder is a well-architected platform that leverages modern design principles to deliver a seamless user experience. The use of multiple design patterns ensures the system is efficient, scalable, and maintainable. With features like multiple login systems, lazy loading, and dynamic page rendering, the platform is both user-friendly and performance-optimized.

