// Activity 1: Working with Objects

// Step 1: Create a Student object
// This function creates a student with name, age, and course
function Student(name, age, course) {
    this.name = name;
    this.age = age;
    this.course = course;
}

// Add a method to introduce the student
Student.prototype.introduce = function() {
    return `Hi, my name is ${this.name} and I am studying ${this.course}.`;
};

// Step 2: Add a method to increase the student's age
Student.prototype.increaseAge = function() {
    this.age += 1; // Add 1 to the student's age
};

// Step 3: Create a student and test the methods
const student1 = new Student("Toff Darell", 20, "Information Technology");

console.log(student1.introduce()); // Show the student's introduction
student1.increaseAge(); // Increase the student's age
console.log(`Updated Age: ${student1.age}`); // Show the updated age

// Activity 2: Using call, apply, and bind

// Step 1: Create a function to describe a person
function describePerson() {
    console.log(`My name is ${this.name}, I am ${this.age} years old and I live in ${this.city}.`);
}

// Step 2: Create a person object
const person1 = {
    name: "Toff Darell",
    age: 20,
    city: "Maramag, Bukidnon"
};

// Step 3: Use call() to run describePerson with person1's data
describePerson.call(person1);

// Step 4: Use apply() to do the same (apply uses an array for arguments)
describePerson.apply(person1);

// Step 5: Use bind() to create a new function that always uses person1's data
const boundDescribe = describePerson.bind(person1);
boundDescribe(); // Run the new function

// Activity 3: Classes and Inheritance

// Step 1: Create a Vehicle class
// This class represents a vehicle with a brand and year
class Vehicle {
    constructor(brand, year) {
        this.brand = brand;
        this.year = year;
    }
    
    // Method to show details about the vehicle
    getDetails() {
        return `This is a ${this.brand} from ${this.year}.`;
    }
}

// Step 2: Create a Car class that extends Vehicle
// This class adds a type (e.g., lambo, Aventador) to the vehicle
class Car extends Vehicle {
    constructor(brand, year, type) {
        super(brand, year); // Call the parent class constructor
        this.type = type; // Add the type property
    }
    
    // Step 3: Override the getDetails method to include the car type
    getDetails() {
        return `This is a ${this.brand} from ${this.year}. It is a ${this.type} car.`;
    }
}
// Step 4: Create a car and test the methods
const myCar = new Car("Lamborghini", 2020, "Aventador");

console.log(myCar.getDetails()); // Show details about the car