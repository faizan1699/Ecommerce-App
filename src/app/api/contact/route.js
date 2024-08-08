import { NextResponse } from "next/server";
import User from "@/app/utils/Models/usersmodal";
import Contact from "@/app/utils/Models/contact";
import { DateTime } from "luxon";

export async function POST(req) {
  try {
    const reqbody = await req.json();

    const { name, email, subject, message } = reqbody;

    const user = await User.findOne({ email });

    if (!user) {
      return NextResponse.json(
        {
          message: "Email not found pls register your account first",
        },
        { status: 404 }
      );
    }

    const userTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
    const currentDateTime = DateTime.now().setZone(userTimezone);

    const userContact = new Contact({
      name,
      email,
      subject,
      message,
      contacttime: currentDateTime.toJSDate(),
    });

    await userContact.save();

    const response = NextResponse.json({
      success: true,
      message: "Your Message Sent Successfully",
    });

    return response;

  } catch (error) {
    return NextResponse.json({ message: error?.message }, { status: 500 });
  }
}
