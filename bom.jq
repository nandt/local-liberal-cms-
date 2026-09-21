(["", "Package", "Version", "Latest", ""] | join("||")),
(.locked[]
  | [ "",
      .name,
      .version,
      .latest,
      ""
    ] | join("|")
)

