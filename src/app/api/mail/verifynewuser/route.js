


import User from "@/app/utils/Models/usersmodal";
import { NextResponse } from "next/server";
import { DateTime } from "luxon";

export async function POST(req) {
 
  try {
  
    const reqbody = await req.json();
    const { token } = reqbody;

    const userTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
    const currentDateTime = DateTime.now().setZone(userTimezone);

    const user = await User.findOne({
      emailverifytoken: token,
      emailverifytokenexp: { $gt: new Date() },
    });

    if (!user) {
      return NextResponse.json({ message: "Token Expired" }, { status: 404 });
    }

    user.emailverify = true;
    user.emailverifytoken = undefined;
    user.emailverifytokenexp = undefined;
    user.userverificationDate = currentDateTime.toJSDate();

    await user.save();

    return NextResponse.json(
      {
        message: "Email verified successfully",
        success: true,
      },
      { status: 200 }
    );

  } catch (error) {

    console.error("Error during email verification:", error);

    return NextResponse.json(
      {
        message: error.message || "An error occurred during email verification",
      },
      { status: 500 }
    );

  }
}
