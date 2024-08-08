import mongoose from "mongoose";

export default async function connect() {
  try {
    await mongoose.connect(process.env.MOONGO_URI, {
      useNewUrlParser: true,
      useUnifiedTopology: true,
    });

    const connection = mongoose.connection;
    console.log("db connected");

    connection.on("connected", () => {
      console.log("mongodb connected");
    });

    connection.on("error", (err) => {
      console.log("mongodb connection err", err);
      process.exit(1);
    });
  } catch (error) {
    console.log("db connection err");
    console.log(error?.message);
  }
}
