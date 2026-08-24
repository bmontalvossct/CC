import unittest
from datetime import datetime, timezone

from classroom_monitor import ClassroomMonitor


class ClassroomMonitorTests(unittest.TestCase):
    def test_attendance_report_tracks_present_and_absent(self) -> None:
        monitor = ClassroomMonitor()
        monitor.add_student("Alice")
        monitor.add_student("Bob")
        monitor.mark_present("Alice")

        report = monitor.attendance_report()

        self.assertEqual(report["total_students"], 2)
        self.assertEqual(report["present_count"], 1)
        self.assertEqual(report["absent_count"], 1)
        self.assertEqual(report["present_students"], ["Alice"])
        self.assertEqual(report["absent_students"], ["Bob"])

    def test_record_activity_and_query_per_student(self) -> None:
        monitor = ClassroomMonitor()
        monitor.add_student("Alice")
        when = datetime(2026, 1, 1, tzinfo=timezone.utc)
        monitor.record_activity("Alice", "answered_question", timestamp=when)

        activities = monitor.activity_report("Alice")

        self.assertEqual(len(activities), 1)
        self.assertEqual(activities[0]["activity"], "answered_question")
        self.assertEqual(activities[0]["timestamp"], when.isoformat())

    def test_record_activity_requires_existing_student(self) -> None:
        monitor = ClassroomMonitor()

        with self.assertRaises(KeyError):
            monitor.record_activity("Alice", "joined_class")

    def test_duplicate_student_raises_error(self) -> None:
        monitor = ClassroomMonitor()
        monitor.add_student("Alice")

        with self.assertRaises(ValueError):
            monitor.add_student("Alice")


if __name__ == "__main__":
    unittest.main()
